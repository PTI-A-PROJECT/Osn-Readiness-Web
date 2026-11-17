<script setup>
import './shared.css'
import HeroSection from './HeroSection.vue'
import ProblemSection from './ProblemSection.vue'
import SolutionSection from './SolutionSection.vue'
import StepsSection from './StepsSection.vue'
import LevelsSection from './LevelsSection.vue'
import CtaSection from './CtaSection.vue'
import LandingFooter from './LandingFooter.vue'
</script>

<template>
  <main class="font-body overflow-x-hidden bg-white text-[#0b1f4b] antialiased">
    <HeroSection />
    <ProblemSection />
    <SolutionSection />
    <StepsSection />
    <LevelsSection />
    <CtaSection />
    <LandingFooter />
  </main>
</template>
