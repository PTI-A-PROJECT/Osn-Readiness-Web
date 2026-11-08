const { chromium } = require('@playwright/test');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  await page.goto('https://mrcln0305.xyz/login');
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForTimeout(3000);

  await page.goto('https://mrcln0305.xyz/siswa/profil');
  await page.waitForTimeout(2000);

  // Click Ubah Kata Sandi
  const btnUbahPw = page.getByRole('button', { name: /Ubah Kata Sandi/i });
  await btnUbahPw.click();
  await page.waitForTimeout(1000);

  const pwForm = page.locator('form').filter({ hasText: /Kata Sandi Saat Ini/i });
  console.log('Password form text:\n', await pwForm.innerText());

  const pwInputs = await pwForm.locator('input').evaluateAll(els => els.map(e => ({ id: e.id, name: e.name, type: e.type, required: e.required })));
  console.log('Password form inputs:', pwInputs);

  // Try submitting without filling
  const btnSimpan = pwForm.getByRole('button', { name: /Simpan Kata Sandi|Simpan/i });
  console.log('Clicking Simpan with empty inputs...');
  await btnSimpan.click();
  await page.waitForTimeout(1500);

  const errorText = await pwForm.innerText();
  console.log('Form text after empty submit:\n', errorText);

  // Check aria-invalid
  const invalidStates = await pwForm.locator('input').evaluateAll(els => els.map(e => ({ id: e.id, ariaInvalid: e.getAttribute('aria-invalid') })));
  console.log('Invalid states:', invalidStates);

  await page.screenshot({ path: 'test-results/profil-password-empty-submit.png', fullPage: true });

  await browser.close();
})();
