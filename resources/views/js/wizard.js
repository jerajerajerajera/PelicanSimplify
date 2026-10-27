/* Wizard helpers for the server creator (draft autosave) and the first Simplify setup (step navigation). */

/**
 * Calls `onChange` when the Filament wizard may have changed step. Livewire 4 can replace the wizard
 * element on re-render, so this does not hold on to one Alpine instance: it re-checks right after
 * clicks and wizard events, with a cheap local fallback check (no requests) every `fallbackMs`.
 */
window.sscWatchWizardStep = function (root, onChange, fallbackMs) {
  const soon = () => setTimeout(onChange, 60);
  const timer = setInterval(onChange, fallbackMs);
  root?.addEventListener?.('click', soon);
  window.addEventListener?.('next-wizard-step', soon);
  return () => {
    clearInterval(timer);
    root?.removeEventListener?.('click', soon);
    window.removeEventListener?.('next-wizard-step', soon);
  };
};

window.sscDraft = function (wire) {
  return {
    deleteConfirm: false, dirty: false, saving: false, editingText: false, switching: false, uploading: false, completed: false,
    status: 'Draft ready', savedCount: 0, hasSaved: true, refreshCount: 0, revision: 0, savedRevision: 0,
    step: 1, furthestStep: 1, stepMenu: false, stopWatching: null, saveTimer: null, pending: null,
    init() {
      this.step = Number(wire.draftStep) || 1;
      this.furthestStep = Math.max(this.step, Number(wire.furthestStep) || 1);
      this.status = wire.draftRestored ? 'Draft restored' : 'Draft ready';
      this.hasSaved = true;
      // The browser's own leave-page warning is the only dialog a page may not restyle.
      this.unload = (event) => {
        if (!this.completed && (this.dirty || this.saving || this.uploading)) {
          event.preventDefault();
          event.returnValue = '';
        }
      };
      window.addEventListener('beforeunload', this.unload);
      window.sscSaveDraft = () => this.flush();
      this.stopWatching = window.sscWatchWizardStep(this.$root, () => this.stepChanged(), 1000);
    },
    stepChanged() {
      const step = this.currentStep();
      this.furthestStep = Math.max(this.furthestStep, step);
      if (step === this.step) return;
      this.step = step;
      this.changed();
      this.flush().catch(() => {});
    },
    wizard() {
      return Alpine.$data(this.$root.querySelector('.fi-sc-wizard'));
    },
    currentStep() {
      try {
        const wizard = this.wizard();
        const index = wizard.getStepIndex(wizard.step);
        return Number.isInteger(index) && index >= 0 ? index + 1 : this.step;
      } catch {
        return this.step;
      }
    },
    async goToStep(target) {
      this.furthestStep = Math.max(this.furthestStep, this.currentStep());
      if (!Number.isInteger(target) || target < 1 || target > this.furthestStep || this.switching || this.uploading) return;
      this.refreshCount++;
      this.switching = true;
      try {
        await this.flush();
        const wizard = this.wizard();
        const steps = wizard.getSteps();
        const selected = steps[target - 1];
        const value = typeof selected === 'string' ? selected : selected?.value;
        if (!value) return;
        // The review summary is rendered on the server; refresh it when jumping straight to it.
        if (target === steps.length) await wire.$refresh?.();
        wizard.step = value;
        this.step = target;
        this.changed();
        await this.flush();
      } catch {
        this.status = 'Could not switch steps — try again';
      } finally {
        this.switching = false;
      }
    },
    /** Only the current step keeps its name when every name does not fit in the bar. */
    fitSteps(nav) {
      nav.classList.remove('is-compact');
      if (nav.scrollWidth > nav.clientWidth + 1) nav.classList.add('is-compact');
    },
    savedVisible() {
      return this.hasSaved && !this.dirty && !this.saving && !this.switching;
    },
    isTextField(target) {
      return target?.tagName === 'TEXTAREA' || (target?.tagName === 'INPUT' && !['checkbox', 'radio', 'file', 'color', 'range', 'button', 'submit', 'hidden'].includes(target.type));
    },
    fieldFocused(event) {
      this.editingText = this.isTextField(event.target);
    },
    fieldChanged(event) {
      this.changed();
      if (!this.isTextField(event.target)) {
        this.refreshCount++;
        this.scheduleSave();
      }
    },
    buttonClicked(event) {
      if (event.target?.closest?.('.ssc-next-button,.ssc-back-button,.fi-sc-wizard-footer .fi-btn,.fi-sc-wizard-actions .fi-btn')) this.refreshCount++;
    },
    fieldExited(event) {
      if (!this.isTextField(event.target)) return;
      this.editingText = false;
      if (this.dirty) this.scheduleSave();
    },
    /** Several quick clicks become one save request. */
    scheduleSave() {
      clearTimeout(this.saveTimer);
      this.saveTimer = setTimeout(() => {
        this.saveTimer = null;
        this.flush(false).catch(() => {});
      }, 1200);
    },
    changed() {
      if (this.completed) return;
      this.revision++;
      this.dirty = true;
      this.status = 'Unsaved changes';
    },
    /** Copies deferred (wire:model) field values into Livewire without sending a request on its own. */
    capture() {
      for (const input of this.$root.querySelectorAll('input,textarea,select')) {
        const model = Array.from(input.attributes).find((a) => a.name === 'wire:model' || a.name.startsWith('wire:model.'));
        if (!model || !model.value.startsWith('data.') || input.disabled || input.type === 'hidden' || input.type === 'file' || model.value === 'data.game' || (input.type === 'radio' && !input.checked)) continue;
        const value = input.type === 'checkbox' ? input.checked : input.value;
        const stored = typeof wire.$get === 'function' ? wire.$get(model.value) : undefined;
        if (stored !== undefined && (input.type === 'checkbox' ? Boolean(stored) === value : String(stored ?? '') === String(value))) continue;
        wire.$set(model.value, value, false);
      }
    },
    async flush(force = true) {
      if (this.completed) return;
      if (this.uploading) {
        this.status = 'Wait for the icon upload to finish';
        throw Error('Upload still in progress');
      }
      clearTimeout(this.saveTimer);
      this.saveTimer = null;
      this.capture();
      if (this.pending) {
        await this.pending;
        if (this.dirty && (force || !this.editingText)) return this.flush(force);
        return;
      }
      const revision = this.revision;
      this.saving = true;
      this.status = 'Saving draft…';
      this.pending = (async () => {
        try {
          await wire.saveDraft(this.currentStep(), Math.max(this.furthestStep, this.currentStep()));
          this.savedRevision = revision;
          this.dirty = this.revision !== revision;
          this.status = this.dirty ? 'Unsaved changes' : 'Draft saved';
          this.hasSaved = true;
          if (!this.dirty) this.savedCount++;
        } catch (error) {
          this.dirty = true;
          this.status = 'Draft not saved — try again';
          throw error;
        } finally {
          this.saving = false;
          this.pending = null;
        }
      })();
      await this.pending;
      if (this.dirty && (force || !this.editingText)) return this.flush(force);
    },
    complete() {
      this.completed = true;
      this.dirty = false;
      this.stopWatching?.();
      clearTimeout(this.saveTimer);
    },
    async discard(approved = false) {
      if (!approved) {
        if (this.saving || this.uploading || this.completed) return;
        // Same confirmation as everywhere else in Simplify; the inline check stays as a fallback.
        if (!window.Simplify?.confirm) {
          this.deleteConfirm = true;
          return;
        }
        const ok = await window.Simplify.confirm({ title: 'Discard this draft?', message: 'Everything you filled in for this server is removed. This cannot be undone.', confirmLabel: 'Discard draft', tone: 'danger' });
        if (!ok) return;
        this.deleteConfirm = true;
      }
      if (!this.deleteConfirm || this.saving || this.uploading || this.completed) return;
      this.deleteConfirm = false;
      this.complete();
      await wire.discardDraft();
    },
    destroy() {
      this.stopWatching?.();
      clearTimeout(this.saveTimer);
      window.removeEventListener('beforeunload', this.unload);
      delete window.sscSaveDraft;
    },
  };
};

/** Step navigation for the first Simplify setup wizard. */
window.simplifySetupProgress = function () {
  return {
    step: 1,
    furthestStep: 1,
    stopWatching: null,
    init() {
      this.$nextTick(() => this.sync());
      this.stopWatching = window.sscWatchWizardStep(this.$root, () => this.sync(), 500);
    },
    wizard() {
      return Alpine.$data(this.$root.querySelector('.fi-sc-wizard'));
    },
    sync() {
      try {
        const wizard = this.wizard();
        const index = wizard.getStepIndex(wizard.step);
        if (Number.isInteger(index) && index >= 0) {
          this.step = index + 1;
          this.furthestStep = Math.max(this.furthestStep, this.step);
        }
      } catch {}
    },
    goToStep(target) {
      this.sync();
      if (!Number.isInteger(target) || target < 1 || target > this.furthestStep) return;
      try {
        const wizard = this.wizard();
        const selected = wizard.getSteps()[target - 1];
        const value = typeof selected === 'string' ? selected : selected?.value;
        if (!value) return;
        wizard.step = value;
        this.sync();
      } catch {}
    },
    destroy() {
      this.stopWatching?.();
    },
  };
};
