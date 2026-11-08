const { chromium } = require('@playwright/test');

async function testProfilActionsAndExamSubmit() {
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

  // 1. TEST PROFIL ACTIONS
  console.log('1. Testing Ubah Profil modal/state...');
  await page.goto('https://mrcln0305.xyz/siswa/profil', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

  const ubahProfilBtn = page.getByRole('button', { name: /Ubah Profil/i });
  await ubahProfilBtn.click();
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'test-results/profil-edit-mode.png' });

  // Check inputs enabled state
  const inputsAfterEdit = await page.$$eval('input', els => els.map(e => ({
    id: e.id,
    disabled: e.disabled,
    value: e.value
  })));
  console.log('Inputs after Ubah Profil clicked:', inputsAfterEdit);

  // Check Ubah Kata Sandi button
  const ubahSandiBtn = page.getByRole('button', { name: /Ubah Kata Sandi/i });
  await ubahSandiBtn.click();
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'test-results/profil-ubah-sandi-modal.png' });
  const sandiModal = page.locator('.modal, [role="dialog"]');
  if (await sandiModal.isVisible()) {
    console.log('Ubah Sandi modal text:\n', await sandiModal.innerText());
    const cancelSandi = sandiModal.locator('button:has-text("Batal")');
    if (await cancelSandi.isVisible()) await cancelSandi.click();
  }

  // 2. TEST SIMULASI QUESTION 30 AND SUBMIT
  console.log('2. Navigating to Simulasi Ujian...');
  await page.goto('https://mrcln0305.xyz/siswa/simulasi/ujian/1', { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);

  // Click question 30
  const btn30 = page.locator('button.nomer:has-text("30")');
  if (await btn30.isVisible()) {
    console.log('Clicking number 30...');
    await btn30.click();
    await page.waitForTimeout(1000);

    const q30Btns = await page.$$eval('button', els => els.map(b => b.innerText.trim()).filter(t => t.length > 0 && t.length < 30));
    console.log('Buttons on question 30:', q30Btns);

    const selesaiBtn = page.getByRole('button', { name: /Selesai/i });
    console.log('Is Selesai visible on Q30:', await selesaiBtn.isVisible());
    if (await selesaiBtn.isVisible()) {
      await selesaiBtn.click();
      await page.waitForTimeout(1000);

      const submitModal = page.locator('.modal, [role="dialog"]');
      console.log('Submit modal text on Q30:\n', await submitModal.innerText());
      await page.screenshot({ path: 'test-results/simulasi-q30-submit-modal.png' });

      // Check "Kumpulkan yang Kosong" button in simulation modal!
      const btnKumpulkanKosong = submitModal.locator('button:has-text("Kumpulkan yang Kosong")');
      if (await btnKumpulkanKosong.isVisible()) {
        console.log('Simulasi "Kumpulkan yang Kosong" isDisabled:', await btnKumpulkanKosong.isDisabled());
      }
    }
  }

  await browser.close();
}

testProfilActionsAndExamSubmit().catch(console.error);
