const { chromium } = require('@playwright/test');

async function checkSimulasiClick() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();

  page.on('console', msg => console.log('CONSOLE:', msg.type(), msg.text()));
  page.on('response', resp => console.log('RESP:', resp.status(), resp.request().method(), resp.url()));

  await page.goto('https://mrcln0305.xyz/login', { waitUntil: 'networkidle' });
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/, { timeout: 15000 });

  await page.goto('https://mrcln0305.xyz/siswa/simulasi', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);

  console.log('Finding Mulai Simulasi button...');
  const startBtn = page.getByRole('button', { name: /Mulai Simulasi/i });
  console.log('Visible:', await startBtn.isVisible());
  console.log('Disabled:', await startBtn.isDisabled());

  // Capture network requests upon click
  await startBtn.click();
  await page.waitForTimeout(2000);

  console.log('Current URL after click:', page.url());
  const modalOrDialog = page.locator('.modal, [role="dialog"], .swal2-modal');
  console.log('Modal visible count:', await modalOrDialog.count());
  if (await modalOrDialog.count() > 0) {
    console.log('Modal text:', await modalOrDialog.first().innerText());
  }

  await page.screenshot({ path: 'test-results/simulasi-click-result.png', fullPage: true });

  await browser.close();
}

checkSimulasiClick().catch(console.error);
