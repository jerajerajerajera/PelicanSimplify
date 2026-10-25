<?php
// PHP only rejects a repeated attribute (such as two #[Renderless] on one method) when Livewire reads it, so the page answers 500. Check every declaration in src/.
function check($condition,$message){if(!$condition)throw new \Exception($message);}
function repeatedAttributes(string $code): array
{
    $found=[];$names=[];$depth=0;$expectName=false;
    foreach(token_get_all($code) as $t){
        [$id,$text,$line]=is_array($t)?$t:[null,$t,0];
        if($id===T_ATTRIBUTE){$depth=1;$expectName=true;continue;}
        if($depth>0){
            if($text==='['||$text==='(')$depth++;
            elseif($text===']'||$text===')')$depth--;
            elseif($text===','&&$depth===1)$expectName=true;
            elseif($expectName&&in_array($id,[T_STRING,T_NAME_QUALIFIED,T_NAME_FULLY_QUALIFIED],true)){
                $name=strtolower(ltrim($text,'\\'));$short=substr(strrchr('\\'.$name,'\\'),1);
                if(isset($names[$short]))$found[]="line $line: #[$text] repeated";
                $names[$short]=true;$expectName=false;
            }
            continue;
        }
        if(in_array($id,[T_FUNCTION,T_FN,T_CLASS,T_INTERFACE,T_TRAIT,T_ENUM,T_VARIABLE,T_CONST],true)||in_array($text,['{','}',';'],true))$names=[];
    }
    return $found;
}
check(repeatedAttributes("<?php class A{\n#[Renderless]\n/** doc */\n#[Renderless]\npublic function a(){}}")!==[],'two #[Renderless] above one method are found, also with a comment between them');
check(repeatedAttributes("<?php class A{\n#[Renderless]\npublic function a(){}\n#[Renderless]\npublic function b(){}}")===[],'the same attribute on two methods is fine');
check(repeatedAttributes("<?php class A{\n#[Locked, \\Livewire\\Attributes\\Locked]\npublic \$a;}")!==[],'a repeat inside one #[...] list or with a full name is found');
$problems=[];
$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../src',FilesystemIterator::SKIP_DOTS));
foreach($files as $file){if($file->getExtension()!=='php')continue;foreach(repeatedAttributes(file_get_contents($file->getPathname())) as $p)$problems[]=$file->getFilename().' '.$p;}
check($problems===[],"repeated attributes:\n".implode("\n",$problems));
echo "PASS no attribute is repeated on one declaration in src/ (Livewire would answer 500)\n";
