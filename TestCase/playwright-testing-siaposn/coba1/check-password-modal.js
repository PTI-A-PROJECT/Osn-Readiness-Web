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
  console.log('Clicking Ubah Kata Sandi...');
  await btnUbahPw.click();
  await page.waitForTimeout(1000);

  const modal = page.locator('.modal, [role="dialog"]');
  console.log('Modal visible?', await modal.isVisible());
  if (await modal.isVisible()) {
    console.log('Modal text:\n', await modal.innerText());
    const inputs = await modal.locator('input').evaluateAll(els => els.map(e => ({ id: e.id, type: e.type, name: e.name, placeholder: e.placeholder })));
    console.log('Modal inputs:', inputs);
    await page.screenshot({ path: 'test-results/profil-modal-password.png' });
  }

  await browser.close();
})();
