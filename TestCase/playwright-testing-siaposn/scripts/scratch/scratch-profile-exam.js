const { chromium } = require('@playwright/test');

async function checkProfileAndExam() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();

  page.on('console', msg => console.log('CONSOLE:', msg.type(), msg.text()));
  page.on('response', resp => {
    if (resp.status() >= 400) {
      console.log('HTTP ERROR:', resp.status(), resp.url());
    }
  });

  await page.goto('https://mrcln0305.xyz/login', { waitUntil: 'networkidle' });
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/, { timeout: 15000 });

  // 1. Inspect /siswa/profil HTML and forms
  console.log('--- INSPECTING /siswa/profil ---');
  await page.goto('https://mrcln0305.xyz/siswa/profil', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

  const profileContent = await page.innerText('main');
  console.log('Profile Text:\n', profileContent);

  const forms = await page.$$eval('form', els => els.map(f => ({
    action: f.action,
    method: f.method,
    inputs: Array.from(f.querySelectorAll('input, button, select')).map(i => ({
      tag: i.tagName,
      type: i.type,
      id: i.id,
      name: i.name,
      value: i.value,
      placeholder: i.placeholder,
      disabled: i.disabled,
      text: i.innerText.trim()
    }))
  })));
  console.log('Profile Forms:', JSON.stringify(forms, null, 2));

  // 2. Inspect /siswa/simulasi/ujian/1
  console.log('--- INSPECTING SIMULASI UJIAN ---');
  await page.goto('https://mrcln0305.xyz/siswa/simulasi/ujian/1', { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);

  console.log('Exam URL:', page.url());
  const examText = await page.innerText('main');
  console.log('Exam Text Snippet:\n', examText.slice(0, 400));

  // Check buttons
  const buttons = await page.$$eval('button', els => els.map(b => ({
    text: b.innerText.trim(),
    className: b.className,
    disabled: b.disabled
  })));
  console.log('Exam buttons:', buttons.filter(b => b.text.length > 0 && b.text.length < 30));

  // Check what happens when clicking Selesai
  const selesaiBtn = page.getByRole('button', { name: /Selesai/i });
  if (await selesaiBtn.isVisible()) {
    console.log('Clicking Selesai button in simulation...');
    await selesaiBtn.click();
    await page.waitForTimeout(1000);

    const modal = page.locator('.modal, [role="dialog"]');
    if (await modal.isVisible()) {
      console.log('Modal appeared on simulation submit:\n', await modal.innerText());
      await page.screenshot({ path: 'test-results/simulasi-submit-modal.png' });

      // Check the buttons in this modal
      const modalBtns = await modal.$$eval('button', els => els.map(b => ({
        text: b.innerText.trim(),
        disabled: b.disabled,
        className: b.className
      })));
      console.log('Modal buttons in simulation:', modalBtns);

      // Close modal
      const cancelBtn = modal.locator('button:has-text("Kembali"), button:has-text("Batal")');
      if (await cancelBtn.isVisible()) {
        await cancelBtn.click();
      }
    }
  }

  // 3. Test Refresh during simulation
  console.log('Testing page reload in simulation...');
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  console.log('URL after reload:', page.url());
  const reloadedText = await page.innerText('main');
  console.log('Reloaded Text Snippet:\n', reloadedText.slice(0, 300));

  await browser.close();
}

checkProfileAndExam().catch(console.error);
