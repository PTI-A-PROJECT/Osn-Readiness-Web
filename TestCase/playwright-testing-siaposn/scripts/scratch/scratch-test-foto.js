const { chromium } = require('@playwright/test');

async function testGantiFoto() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://mrcln0305.xyz/login');
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/);
  await page.goto('https://mrcln0305.xyz/siswa/profil');

  await page.getByRole('button', { name: 'Ganti foto profil' }).click();
  await page.waitForTimeout(1000);

  const textSec1 = await page.locator('section:has-text("01 · EDIT PROFIL")').first().innerText();
  console.log('Section 01 text after clicking Ganti foto profil:\n', textSec1);

  // Check toast or alert
  const alerts = await page.locator('.toast, .alert, [role="alert"]').allInnerTexts();
  console.log('Alerts:', alerts);

  await browser.close();
}

testGantiFoto().catch(console.error);
