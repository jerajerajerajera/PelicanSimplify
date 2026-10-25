// Web Push encryption and VAPID signature, checked with Node's own crypto (an independent implementation of RFC 8291 / ES256).
const crypto=require('crypto'),assert=require('assert'),{execFileSync}=require('child_process');
const php=process.env.PHP_BIN||'php';
const b64=(b)=>Buffer.from(b).toString('base64url');
const receiver=crypto.createECDH('prime256v1');receiver.generateKeys();
const auth=crypto.randomBytes(16);
const sub={endpoint:'https://fcm.googleapis.com/fcm/send/abc123',p256dh:b64(receiver.getPublicKey()),auth:b64(auth)};
const message=JSON.stringify({title:'Maintenance on Sunday',body:'Restart between 03:00 and 04:00. Save your builds ✓',url:'/simplify'});
const script=`require ${JSON.stringify(__dirname+'/../src/Services/WebPush.php')};use Simplify\\Panel\\Services\\WebPush;$in=json_decode(stream_get_contents(STDIN),true);$keys=WebPush::createKeys();
$checks=['allowed'=>array_map(fn($u)=>WebPush::endpointAllowed($u),$in['urls']),'valid'=>[WebPush::keysValid($in['sub']['p256dh'],$in['sub']['auth']),WebPush::keysValid('AAAA',$in['sub']['auth'])]];
try{WebPush::request(['endpoint'=>'https://evil.example/x']+$in['sub'],'x',$keys,'mailto:a@b.c');$checks['refused']=false;}catch(\\InvalidArgumentException){$checks['refused']=true;}
$r=WebPush::request($in['sub'],$in['message'],$keys,'https://panel.example',3600,'high',1800000000);$r['body']=base64_encode($r['body']);
echo json_encode(['request'=>$r,'public'=>$keys['public'],'checks'=>$checks]);`;
const urls=['https://fcm.googleapis.com/fcm/send/x','https://updates.push.services.mozilla.com/wpush/v2/x','https://web.push.apple.com/Q','https://wns2-db5p.notify.windows.com/w/?token=x','http://fcm.googleapis.com/x','https://fcm.googleapis.com.evil.example/x','https://evil.example/fcm.googleapis.com','https://127.0.0.1/x'];
const out=JSON.parse(execFileSync(php,['-r',script],{input:JSON.stringify({sub,message,urls})}).toString());
assert.deepEqual(out.checks.allowed,[true,true,true,true,false,false,false,false],'only https endpoints of the browsers\' push services');
assert.deepEqual(out.checks.valid,[true,false],'browser keys are checked');
assert.equal(out.checks.refused,true,'a request to another host is refused');
const req=out.request;
assert.equal(req.url,sub.endpoint);
assert.equal(req.headers['Content-Encoding'],'aes128gcm');assert.equal(req.headers.TTL,'3600');assert.equal(req.headers.Urgency,'high');
// Decrypt as the browser does.
const body=Buffer.from(req.body,'base64');
const salt=body.subarray(0,16),rs=body.readUInt32BE(16),idlen=body[20],senderPublic=body.subarray(21,21+idlen),cipher=body.subarray(21+idlen);
assert.equal(rs,4096);assert.equal(idlen,65);
const shared=receiver.computeSecret(senderPublic);
const hmac=(key,data)=>crypto.createHmac('sha256',key).update(data).digest();
const hkdf=(s,ikm,info,len)=>hmac(hmac(s,ikm),Buffer.concat([info,Buffer.from([1])])).subarray(0,len);
const ikm=hkdf(auth,shared,Buffer.concat([Buffer.from('WebPush: info\0'),receiver.getPublicKey(),senderPublic]),32);
const cek=hkdf(salt,ikm,Buffer.from('Content-Encoding: aes128gcm\0'),16),nonce=hkdf(salt,ikm,Buffer.from('Content-Encoding: nonce\0'),12);
const decipher=crypto.createDecipheriv('aes-128-gcm',cek,nonce);decipher.setAuthTag(cipher.subarray(cipher.length-16));
const plain=Buffer.concat([decipher.update(cipher.subarray(0,cipher.length-16)),decipher.final()]);
assert.equal(plain[plain.length-1],2,'last-record padding delimiter');
assert.equal(plain.subarray(0,plain.length-1).toString('utf8'),message,'the browser decrypts the message');
// VAPID: the JWT verifies with the public key in k=, for the push service's origin.
const m=/^vapid t=([^,]+), k=(.+)$/.exec(req.headers.Authorization);assert.ok(m,'vapid authorization header');
assert.equal(m[2],out.public);
const [h,p,sig]=m[1].split('.');
const claims=JSON.parse(Buffer.from(p,'base64url'));
assert.deepEqual(claims,{aud:'https://fcm.googleapis.com',exp:1800000000+12*3600,sub:'https://panel.example'});
assert.deepEqual(JSON.parse(Buffer.from(h,'base64url')),{typ:'JWT',alg:'ES256'});
const point=Buffer.from(out.public,'base64url');
const key=crypto.createPublicKey({key:{kty:'EC',crv:'P-256',x:b64(point.subarray(1,33)),y:b64(point.subarray(33))},format:'jwk'});
assert.ok(crypto.verify('sha256',Buffer.from(h+'.'+p),{key,dsaEncoding:'ieee-p1363'},Buffer.from(sig,'base64url')),'ES256 signature verifies');
console.log('PASS web push: aes128gcm decrypts in Node, VAPID JWT verifies, only browser push services, key checks');
