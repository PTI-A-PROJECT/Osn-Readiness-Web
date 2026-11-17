import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('vue-router', () => ({
  useRoute: vi.fn(() => ({ name: 'login' })),
  RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' },
}))

import { useRoute } from 'vue-router'
import LandingPage from '@/views/LandingPage.vue'
import FeaturesSection from '@/components/landing/FeaturesSection.vue'
import PublicNavbar from '@/components/PublicNavbar.vue'

describe('FeaturesSection', () => {
  it('menampilkan enam kartu fitur dengan judul unik', () => {
    const wrapper = mount(FeaturesSection)
    const titles = wrapper.findAll('article h3').map((h) => h.text())
    expect(titles).toHaveLength(6)
    expect(new Set(titles).size).toBe(6)
  })

  it('setiap kartu punya deskripsi dan tag', () => {
    const wrapper = mount(FeaturesSection)
    wrapper.findAll('article').forEach((card) => {
      const paragraphs = card.findAll('p')
      expect(paragraphs).toHaveLength(2)
      paragraphs.forEach((p) => expect(p.text().length).toBeGreaterThan(0))
    })
  })
})

describe('LandingPage', () => {
  it('semua tautan #anchor menuju id yang ada di halaman', () => {
    const wrapper = mount(LandingPage, { attachTo: document.body })
    const hrefs = wrapper
      .findAll('a[href^="#"]')
      .map((a) => a.attributes('href'))
      .filter((h) => h.length > 1)
    expect(hrefs.length).toBeGreaterThan(0)
    hrefs.forEach((h) => {
      expect(document.getElementById(h.slice(1)), `id untuk ${h} tidak ditemukan`).not.toBeNull()
    })
    wrapper.unmount()
  })

  it('memuat seluruh section utama', () => {
    const wrapper = mount(LandingPage, { attachTo: document.body })
    ;['beranda', 'kenapa', 'fitur', 'cara-kerja', 'tingkat', 'tim'].forEach((id) => {
      expect(document.getElementById(id), `section #${id}`).not.toBeNull()
    })
    wrapper.unmount()
  })
})

describe('PublicNavbar', () => {
  it('disembunyikan di halaman landing', () => {
    useRoute.mockReturnValueOnce({ name: 'landing' })
    const wrapper = mount(PublicNavbar)
    expect(wrapper.find('header').exists()).toBe(false)
  })

  it('menyembunyikan tombol Masuk saat berada di halaman login', () => {
    const wrapper = mount(PublicNavbar, { props: { current: 'login' } })
    const text = wrapper.find('.pubnav__actions').text()
    expect(text).not.toContain('Masuk')
    expect(text).toContain('Daftar Gratis')
  })
})
