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

  const btnSalin = page.getByRole('button', { name: 'Salin' });
  console.log('Salin button visible?', await btnSalin.isVisible());
  if (await btnSalin.isVisible()) {
    await btnSalin.click();
    await page.waitForTimeout(500);
    console.log('Salin button text after click:', await btnSalin.innerText());
    const toasts = await page.locator('.toast, .notification, .alert, [role="alert"]').allInnerTexts();
    console.log('Any toast or alert after copy?', toasts);
  }

  await browser.close();
})();
