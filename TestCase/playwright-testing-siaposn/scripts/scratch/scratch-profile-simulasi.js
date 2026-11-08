const { chromium } = require('@playwright/test');

async function testProfileAndSimulasi() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();

  page.on('console', msg => console.log('CONSOLE:', msg.type(), msg.text()));
  page.on('response', resp => {
    if (resp.status() >= 400) {
      console.log('HTTP ERROR:', resp.status(), resp.request().method(), resp.url());
    }
  });

  await page.goto('https://mrcln0305.xyz/login', { waitUntil: 'networkidle' });
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/, { timeout: 15000 });

  // 1. Check Profil Akun
  console.log('Testing Profil Akun...');
  const userBtn = page.locator('button:has-text("testqa6"), div:has-text("testqa6")').last();
  await userBtn.click();
  await page.waitForTimeout(500);

  const profilLink = page.locator('text=Profil Akun');
  if (await profilLink.isVisible()) {
    await profilLink.click();
    await page.waitForTimeout(2000);
    console.log('URL after clicking Profil Akun:', page.url());
    await page.screenshot({ path: 'test-results/profil-page.png', fullPage: true });

    // Check forms/inputs on profile page
    const inputs = await page.$$eval('input', els => els.map(e => ({ id: e.id, name: e.name, type: e.type, value: e.value, disabled: e.disabled })));
    console.log('Profile inputs:', inputs);

    // Try submitting profile with empty or invalid data
    const saveBtn = page.locator('button:has-text("Simpan"), button:has-text("Update"), button:has-text("Ubah")');
    console.log('Save button count:', await saveBtn.count());
  }

  // 2. Check Simulasi Modal -> Start Simulasi
  console.log('Testing Simulasi modal submission...');
  await page.goto('https://mrcln0305.xyz/siswa/simulasi', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);

  await page.getByRole('button', { name: /Mulai Simulasi/i }).click();
  await page.waitForTimeout(1000);

  const modalStartBtn = page.locator('.modal, [role="dialog"]').locator('button:has-text("Mulai Simulasi")');
  console.log('Modal Start button disabled before check:', await modalStartBtn.isDisabled());

  // Check agreement checkbox
  const checkbox = page.locator('.modal, [role="dialog"]').locator('input[type="checkbox"]');
  await checkbox.check();
  await page.waitForTimeout(300);
  console.log('Modal Start button disabled after check:', await modalStartBtn.isDisabled());

  // Click start
  await modalStartBtn.click();
  await page.waitForTimeout(3000);
  console.log('URL after starting simulasi:', page.url());
  await page.screenshot({ path: 'test-results/simulasi-room.png', fullPage: true });

  const roomText = await page.innerText('body');
  console.log('Room text preview:\n', roomText.slice(0, 400));

  await browser.close();
}

testProfileAndSimulasi().catch(console.error);
