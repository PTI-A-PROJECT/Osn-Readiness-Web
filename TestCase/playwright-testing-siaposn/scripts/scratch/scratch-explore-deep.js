const { chromium } = require('@playwright/test');
const fs = require('fs');

async function explore() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1280, height: 800 }
  });
  const page = await context.newPage();

  const logs = [];
  const errors = [];
  const networkErrors = [];

  page.on('console', msg => {
    logs.push(`[CONSOLE ${msg.type()}] ${msg.text()}`);
    if (msg.type() === 'error') {
      errors.push(`Console Error: ${msg.text()} (${msg.location()?.url}:${msg.location()?.lineNumber})`);
    }
  });

  page.on('pageerror', err => {
    errors.push(`Page Error: ${err.message}\nStack: ${err.stack}`);
  });

  page.on('response', async resp => {
    if (resp.status() >= 400) {
      let body = '';
      try { body = await resp.text(); } catch (e) {}
      networkErrors.push({
        status: resp.status(),
        method: resp.request().method(),
        url: resp.url(),
        body: body.slice(0, 300)
      });
    }
  });

  // 1. LOGIN
  console.log('1. Logging in...');
  await page.goto('https://mrcln0305.xyz/login', { waitUntil: 'networkidle' });
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/, { timeout: 15000 });
  await page.screenshot({ path: 'test-results/exp-01-dashboard.png', fullPage: true });

  // 2. DASHBOARD INSPECTION
  console.log('2. Inspecting Dashboard...');
  const dashLinks = await page.$$eval('a', els => els.map(a => ({ text: a.innerText.trim(), href: a.href })));
  console.log('Dashboard Links:', JSON.stringify(dashLinks, null, 2));

  // Check Profile / Dropdown
  const userMenuBtn = page.locator('text=testqa6').first();
  if (await userMenuBtn.isVisible()) {
    console.log('User menu button visible');
    await userMenuBtn.click();
    await page.waitForTimeout(500);
    await page.screenshot({ path: 'test-results/exp-01-user-menu.png' });
  }

  // 3. PEMETAAN KOMPETENSI
  console.log('3. Inspecting Pemetaan Kompetensi...');
  await page.goto('https://mrcln0305.xyz/siswa/pemetaan', { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'test-results/exp-02-pemetaan.png', fullPage: true });
  
  // Check "Unduh laporan"
  const unduhBtn = page.getByRole('button', { name: /Unduh laporan/i });
  if (await unduhBtn.isVisible()) {
    console.log('Testing Unduh Laporan...');
    try {
      const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 5000 }),
        unduhBtn.click()
      ]);
      const downloadPath = await download.path();
      const filename = download.suggestedFilename();
      const stats = fs.statSync(downloadPath);
      console.log(`Downloaded file: ${filename}, size: ${stats.size} bytes`);
      // read snippet
      const content = fs.readFileSync(downloadPath);
      console.log('File preview (first 100 bytes):', content.slice(0, 100).toString('utf-8'));
    } catch (e) {
      console.log('Unduh laporan failed or timed out:', e.message);
    }
  }

  // 4. MATERI
  console.log('4. Inspecting Materi...');
  await page.goto('https://mrcln0305.xyz/siswa/materi', { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'test-results/exp-03-materi.png', fullPage: true });

  const materiCards = await page.$$eval('.card, article, div:has(> h3), div:has(> h4)', els => els.map(e => e.innerText.slice(0, 100)));
  console.log('Materi items count / preview:', materiCards.length);

  // Inspect detail materi 1
  console.log('4b. Inspecting Detail Materi 1...');
  await page.goto('https://mrcln0305.xyz/siswa/materi/1', { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'test-results/exp-04-materi-detail-1.png', fullPage: true });

  // 5. LATIHAN
  console.log('5. Inspecting Latihan...');
  await page.goto('https://mrcln0305.xyz/siswa/latihan/1', { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'test-results/exp-05-latihan-1.png', fullPage: true });

  // Check quiz buttons, numbers, submit
  const nomorBtns = await page.$$eval('button.nomer', els => els.map(e => e.innerText.trim()));
  console.log('Nomor buttons:', nomorBtns);

  // Test invalid latihan ID
  console.log('5b. Testing Invalid Latihan ID...');
  await page.goto('https://mrcln0305.xyz/siswa/latihan/9999', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'test-results/exp-05b-invalid-latihan.png', fullPage: true });
  const errorPageText = await page.innerText('body');
  console.log('Invalid latihan response snippet:', errorPageText.slice(0, 300));

  // 6. SIMULASI SELEKSI
  console.log('6. Inspecting Simulasi Seleksi...');
  await page.goto('https://mrcln0305.xyz/siswa/simulasi', { waitUntil: 'networkidle' });
  await page.waitForTimeout(3000);
  await page.screenshot({ path: 'test-results/exp-06-simulasi.png', fullPage: true });
  const simulasiText = await page.innerText('main, body');
  console.log('Simulasi page snippet:', simulasiText.slice(0, 400));

  // 7. RIWAYAT HASIL
  console.log('7. Inspecting Riwayat Hasil...');
  await page.goto('https://mrcln0305.xyz/siswa/riwayat', { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'test-results/exp-07-riwayat.png', fullPage: true });
  const riwayatText = await page.innerText('main, body');
  console.log('Riwayat page snippet:', riwayatText.slice(0, 400));

  // 8. 404 / NON-EXISTENT ROUTE
  console.log('8. Testing Non-existent Route...');
  await page.goto('https://mrcln0305.xyz/siswa/halaman-tidak-ada', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'test-results/exp-08-404.png', fullPage: true });
  console.log('404 Page URL & title:', page.url(), await page.title());

  console.log('\n--- NETWORK ERRORS REPORT ---');
  console.log(JSON.stringify(networkErrors, null, 2));

  console.log('\n--- CONSOLE / PAGE ERRORS ---');
  console.log(JSON.stringify(errors, null, 2));

  fs.writeFileSync('test-results/exploration-report.json', JSON.stringify({
    errors,
    networkErrors,
    logs: logs.slice(-50)
  }, null, 2));

  await browser.close();
}

explore().catch(console.error);
