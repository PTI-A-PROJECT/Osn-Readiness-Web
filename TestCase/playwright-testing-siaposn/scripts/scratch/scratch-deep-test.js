const { chromium } = require('@playwright/test');
const fs = require('fs');

async function testDeepFlows() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();

  const networkErrors = [];
  const consoleErrors = [];

  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push({ text: msg.text(), url: msg.location()?.url, line: msg.location()?.lineNumber });
    }
  });

  page.on('response', async resp => {
    if (resp.status() >= 400) {
      let body = '';
      try { body = await resp.text(); } catch (e) {}
      networkErrors.push({
        status: resp.status(),
        url: resp.url(),
        method: resp.request().method(),
        body: body.slice(0, 300)
      });
    }
  });

  // Login
  console.log('Logging in...');
  await page.goto('https://mrcln0305.xyz/login', { waitUntil: 'networkidle' });
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/, { timeout: 15000 });

  // A. USER MENU & PROFILE
  console.log('Testing User Menu & Profile...');
  const userBtn = page.locator('button:has-text("testqa6"), div:has-text("testqa6")').last();
  await userBtn.click();
  await page.waitForTimeout(500);
  const dropdownItems = await page.$$eval('a, button', els => els.filter(e => e.offsetParent !== null).map(e => ({ text: e.innerText.trim(), href: e.href || null })));
  console.log('User dropdown visible items:', dropdownItems.filter(i => i.text.length > 0 && i.text.length < 50));
  await page.screenshot({ path: 'test-results/deep-user-dropdown.png' });

  // Check if there is profile link or logout
  const logoutBtn = page.locator('button:has-text("Keluar"), a:has-text("Keluar"), button:has-text("Logout"), a:has-text("Logout")');
  console.log('Logout button found:', await logoutBtn.count());

  // B. TEST MATERI TABS & FILTERS
  console.log('Testing Materi Filters...');
  await page.goto('https://mrcln0305.xyz/siswa/materi', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  // See available filters / tabs
  const filterBtns = await page.$$eval('button, select, [role="tab"]', els => els.map(e => e.innerText.trim() || e.id || e.className));
  console.log('Materi filter buttons/tabs:', filterBtns);

  // Click each filter or select
  const selects = await page.$$eval('select', els => els.map(s => ({
    name: s.name,
    id: s.id,
    options: Array.from(s.options).map(o => ({ text: o.text, value: o.value, disabled: o.disabled }))
  })));
  console.log('Select elements on materi:', JSON.stringify(selects, null, 2));

  // Try clicking tingkat selector if any
  const provinsiOption = page.locator('text=Provinsi, text=Tingkat Provinsi');
  console.log('Provinsi option count:', await provinsiOption.count());

  // C. TEST SIMULASI SELEKSI PAGE & START SIMULATION
  console.log('Testing Simulasi Seleksi...');
  await page.goto('https://mrcln0305.xyz/siswa/simulasi', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  const simulasiBtns = await page.$$eval('button, a.btn', els => els.map(e => ({ text: e.innerText.trim(), disabled: e.disabled })));
  console.log('Simulasi buttons:', simulasiBtns);

  // Look for "Mulai Simulasi"
  const startSimBtn = page.getByRole('button', { name: /Mulai Simulasi/i }).or(page.locator('button:has-text("Mulai Simulasi")'));
  console.log('Start simulasi button count:', await startSimBtn.count());
  if (await startSimBtn.count() > 0) {
    const isStartDisabled = await startSimBtn.first().isDisabled();
    console.log('Start Simulasi is disabled:', isStartDisabled);
    if (!isStartDisabled) {
      console.log('Clicking Mulai Simulasi...');
      await startSimBtn.first().click();
      await page.waitForTimeout(2000);
      console.log('URL after clicking Mulai Simulasi:', page.url());
      await page.screenshot({ path: 'test-results/deep-simulasi-started.png', fullPage: true });

      // Check if inside exam
      if (page.url().includes('ujian') || page.url().includes('simulasi/')) {
        // Test refresh during simulation (critical QA test case!)
        console.log('Testing page reload / refresh during simulation...');
        await page.reload({ waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        console.log('URL after reload during simulation:', page.url());
        const afterReloadText = await page.innerText('body');
        console.log('Text after reload snippet:', afterReloadText.slice(0, 300));
        await page.screenshot({ path: 'test-results/deep-simulasi-after-reload.png', fullPage: true });
      }
    }
  }

  // D. TEST RIWAYAT HASIL DETAIL
  console.log('Testing Riwayat Hasil details...');
  await page.goto('https://mrcln0305.xyz/siswa/riwayat', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  const riwayatCards = await page.$$eval('article, .card, tr, div:has(> button)', els => els.map(e => e.innerText.slice(0, 150)));
  console.log('Riwayat items count:', riwayatCards.length);
  console.log('Riwayat sample:', riwayatCards.slice(0, 3));

  // Check detail buttons in riwayat
  const detailBtns = page.locator('button:has-text("Detail"), a:has-text("Detail"), button:has-text("Lihat"), a:has-text("Lihat")');
  console.log('Detail buttons in riwayat count:', await detailBtns.count());
  if (await detailBtns.count() > 0) {
    await detailBtns.first().click();
    await page.waitForTimeout(2000);
    console.log('URL after clicking detail:', page.url());
    await page.screenshot({ path: 'test-results/deep-riwayat-detail.png', fullPage: true });
    console.log('Detail text snippet:', (await page.innerText('body')).slice(0, 300));
  }

  // E. TEST 404 AND UNAUTHENTICATED REDIRECTION
  console.log('Testing 404 page content...');
  await page.goto('https://mrcln0305.xyz/siswa/non-existent-page-test-404', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  console.log('404 content snippet:', (await page.innerText('body')).slice(0, 300));

  // Save report
  fs.writeFileSync('test-results/deep-flow-report.json', JSON.stringify({
    networkErrors,
    consoleErrors
  }, null, 2));

  await browser.close();
}

testDeepFlows().catch(console.error);
