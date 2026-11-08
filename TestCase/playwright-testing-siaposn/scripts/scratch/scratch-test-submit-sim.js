const { chromium } = require('@playwright/test');

async function testSubmitSim() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  page.on('request', r => console.log('REQ:', r.method(), r.url()));
  page.on('response', async r => {
    console.log('RESP:', r.status(), r.url());
    if (r.status() >= 400) {
      try { console.log('ERR BODY:', await r.text()); } catch (e) {}
    }
  });

  await page.goto('https://mrcln0305.xyz/login');
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/);
  await page.goto('https://mrcln0305.xyz/siswa/simulasi/ujian/1');
  await page.waitForTimeout(1500);

  // Click 30
  await page.locator('button.nomer:has-text("30")').click();
  await page.waitForTimeout(500);

  // Click Selesai
  await page.getByRole('button', { name: /Selesai/i }).click();
  await page.waitForTimeout(1000);

  console.log('Modal visible:', await page.locator('.modal, [role="dialog"]').isVisible());
  const btnKumpulkan = page.locator('button:has-text("Kumpulkan yang Kosong")');
  console.log('Btn kumpulkan disabled:', await btnKumpulkan.isDisabled());

  await btnKumpulkan.click();
  await page.waitForTimeout(3000);

  console.log('Current URL after click:', page.url());
  await page.screenshot({ path: 'test-results/simulasi-after-kumpulkan-click.png' });

  await browser.close();
}

testSubmitSim().catch(console.error);
