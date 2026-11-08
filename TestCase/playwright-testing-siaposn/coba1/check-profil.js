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

  // Click Ubah Profil
  const btnUbah = page.getByRole('button', { name: /Ubah Profil/i });
  if (await btnUbah.isVisible()) {
    await btnUbah.click();
    await page.waitForTimeout(1000);
    console.log('Clicked Ubah Profil. Available buttons now:', await page.locator('main').first().locator('button').allInnerTexts());
    const inputsAfter = await page.locator('input').evaluateAll(els => els.map(e => ({ id: e.id, name: e.name, disabled: e.disabled, val: e.value })));
    console.log('Inputs after edit mode:', inputsAfter);
  }

  // Check Password change section
  console.log('Checking Password Change inputs...');
  const pwSection = page.locator('section, div').filter({ hasText: /Ubah Kata Sandi/i });
  const pwInputs = await pwSection.locator('input').evaluateAll(els => els.map(e => ({ id: e.id, type: e.type, placeholder: e.placeholder })));
  console.log('Password section inputs:', pwInputs);

  // Try submitting password change with empty fields
  const btnSimpanPassword = pwSection.getByRole('button', { name: /Simpan|Ubah|Perbarui/i }).first();
  if (await btnSimpanPassword.isVisible()) {
    console.log('Simpan password button text:', await btnSimpanPassword.innerText());
    await btnSimpanPassword.click();
    await page.waitForTimeout(1000);
    const alertMsg = await page.locator('.error, .alert, .text-red-500, [role="alert"]').allInnerTexts();
    console.log('Password change feedback:', alertMsg);
  }

  await browser.close();
})();
