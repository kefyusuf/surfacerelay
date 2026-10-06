const element = (id) => document.getElementById(id);
let csrf = '';
async function request(path, payload) {
  const response = await fetch(path, {method: payload ? 'POST' : 'GET', credentials: 'same-origin',
    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
    ...(payload ? {body: JSON.stringify(payload)} : {})});
  return {response, body: await response.json()};
}
const ownerSession = await request('/session'); csrf = ownerSession.body.csrfToken;
const state = await request('/checkout/status');
element('checkout-summary').textContent = state.response.ok ? JSON.stringify(state.body, null, 2) : 'Checkout unavailable in this session/context.';
element('verify-code').addEventListener('click', async () => {
  const button = element('verify-code'); button.disabled = true;
  try {
    const result = await request(location.pathname + '/verify', {code: element('test-code').value});
    element('test-code').value = '';
    element('verification-result').textContent = JSON.stringify(result.body, null, 2);
  } catch { element('verification-result').textContent = 'Verification request failed; no payment is confirmed.'; }
  finally { button.disabled = false; }
});
