const { chromium } = require('@playwright/test');

async function testGhostClick() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  page.on('console', m => console.log('LOG:', m.text()));
  page.on('pageerror', e => console.log('PAGEERR:', e.message));

  await page.goto('https://mrcln0305.xyz/login');
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/);
  await page.goto('https://mrcln0305.xyz/siswa/simulasi/ujian/1');
  await page.waitForTimeout(1500);

  // Click 30 -> Selesai
  await page.locator('button.nomer:has-text("30")').click();
  await page.waitForTimeout(500);
  await page.getByRole('button', { name: /Selesai/i }).click();
  await page.waitForTimeout(1000);

  // Click with locator
  const ghostBtn = page.locator('button.mbtn-ghost');
  console.log('Ghost button visible:', await ghostBtn.isVisible());
  console.log('Clicking ghost button...');

  page.on('request', r => console.log('REQ:', r.method(), r.url()));
  page.on('response', r => console.log('RESP:', r.status(), r.url()));

  await ghostBtn.click();
  await page.waitForTimeout(4000);

  console.log('URL after clicking ghost button:', page.url());
  const dialogStillOpen = await page.locator('.modal, [role="dialog"]').isVisible();
  console.log('Is modal still open:', dialogStillOpen);

  await browser.close();
}

testGhostClick().catch(console.error);
