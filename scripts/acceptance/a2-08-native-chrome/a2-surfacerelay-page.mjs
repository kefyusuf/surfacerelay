/**
 * A2-08 app-owned page registration module for the read-only A2-02 consumer.
 *
 * Self-guarding: it registers the server-issued descriptor through the glue
 * ONLY when BOTH (a) the surface exposes document.modelContext and (b) a
 * mounted Livewire component carries `surfacerelayDescriptor`. The login page
 * and unrelated pages therefore register nothing. Discovery metadata carries
 * no authority: the server re-checks actor, tenant, record and binding on
 * every dispatch. The lease is disposed on Livewire navigation and re-created
 * when a descriptor-carrying component is mounted again. This module never
 * throws into the host page.
 */

import { createA2ToolRegistration } from './a2-surfacerelay-glue.mjs';

let lease = null;
let registering = false;
let pollAttempt = 0;

// Diagnostic marker for the acceptance harness: proves the module executed
// even when registration later fails closed.
window.__surfacerelayA2Registration = { registered: false, reason: 'module_loaded' };

function descriptorFromMountedComponents() {
  if (!window.Livewire || typeof window.Livewire.all !== 'function') return null;
  for (const component of window.Livewire.all()) {
    const wire = component?.$wire;
    if (!wire || typeof wire.get !== 'function') continue;
    let descriptor = null;
    try {
      descriptor = wire.get('surfacerelayDescriptor');
    } catch {
      continue;
    }
    if (descriptor && descriptor.definition && descriptor.binding) {
      return { descriptor, componentId: wire.$id ?? null };
    }
  }
  return null;
}

function note(state) {
  // Small diagnostic surface for the local acceptance harness; never authority.
  window.__surfacerelayA2Registration = { ...state, at: new Date().toISOString() };
}

async function register() {
  if (lease || registering) return;
  const found = descriptorFromMountedComponents();
  if (!found) return;
  registering = true;
  try {
    const handle = await createA2ToolRegistration({
      definition: found.descriptor.definition,
      binding: found.descriptor.binding,
      doc: document,
      livewireRoot: window,
      selectionRoot: window,
    });
    lease = handle.registered ? handle.lease : null;
    note({
      registered: handle.registered,
      reason: handle.reason,
      componentId: found.componentId,
    });
  } catch {
    lease = null;
    note({ registered: false, reason: 'registration_threw' });
  } finally {
    registering = false;
  }
}

function dispose() {
  if (!lease) return;
  try {
    lease.dispose();
  } catch {
    // never break the host page
  }
  lease = null;
  note({ registered: false, reason: 'disposed' });
}

function pollForRegistration() {
  void register();
  if (lease || pollAttempt > 60) {
    pollAttempt = 0;
    return;
  }
  pollAttempt += 1;
  setTimeout(pollForRegistration, 50);
}

window.addEventListener('livewire:navigating', dispose);
window.addEventListener('livewire:navigated', () => {
  dispose();
  pollAttempt = 0;
  pollForRegistration();
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', pollForRegistration);
} else {
  pollForRegistration();
}
