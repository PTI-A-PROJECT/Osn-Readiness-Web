const { chromium } = require('@playwright/test');

async function testPasswordChangeFlow() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  page.on('response', async resp => {
    if (resp.url().includes('/api/')) {
      let body = '';
      try { body = await resp.text(); } catch (e) {}
      console.log(`[API ${resp.status()}] ${resp.request().method()} ${resp.url()} -> ${body.slice(0, 150)}`);
    }
  });

  await page.goto('https://mrcln0305.xyz/login');
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/);
  await page.goto('https://mrcln0305.xyz/siswa/profil');

  await page.getByRole('button', { name: 'Ubah Kata Sandi' }).click();
  await page.waitForTimeout(500);

  // 1. Submit empty fields
  console.log('--- TEST: Submit empty fields ---');
  await page.getByRole('button', { name: 'Simpan' }).click();
  await page.waitForTimeout(1000);

  const errorsEmpty = await page.locator('section:has-text("02 · KEAMANAN") .text-red-500, section:has-text("02 · KEAMANAN") .error').allInnerTexts();
  console.log('Errors on empty submit in Section 02:', errorsEmpty);

  // 2. Submit wrong current password
  console.log('--- TEST: Submit wrong current password ---');
  await page.locator('#pw-now').fill('wrongpassword123');
  await page.locator('#pw-new').fill('NewPassword123!');
  await page.locator('#pw-confirm').fill('NewPassword123!');
  await page.getByRole('button', { name: 'Simpan' }).click();
  await page.waitForTimeout(1500);

  const alerts = await page.locator('.alert, [role="alert"], .text-red-500').allInnerTexts();
  console.log('Alerts on wrong current password:', alerts);

  await page.screenshot({ path: 'test-results/profil-pw-wrong-current.png' });

  await browser.close();
}

testPasswordChangeFlow().catch(console.error);
