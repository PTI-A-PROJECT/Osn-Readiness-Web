const { chromium } = require('@playwright/test');

async function submitActiveSimulasi() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
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

  // Click Kumpulkan yang Kosong
  const submitBtn = page.locator('button:has-text("Kumpulkan yang Kosong")');
  if (await submitBtn.isVisible()) {
    console.log('Submitting simulation...');
    await submitBtn.click();
    await page.waitForTimeout(3000);
    console.log('URL after submit:', page.url());
  }

  await browser.close();
}

submitActiveSimulasi().catch(console.error);
