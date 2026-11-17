<script setup>
import HeroSection from '@/components/landing/HeroSection.vue'
import ProblemSection from '@/components/landing/ProblemSection.vue'
import SolutionSection from '@/components/landing/SolutionSection.vue'
import StepsSection from '@/components/landing/StepsSection.vue'
import LevelsSection from '@/components/landing/LevelsSection.vue'
import CtaSection from '@/components/landing/CtaSection.vue'
import LandingFooter from '@/components/landing/LandingFooter.vue'
</script>

<template>
  <main class="overflow-x-hidden bg-white">
    <HeroSection />
    <ProblemSection />
    <SolutionSection />
    <StepsSection />
    <LevelsSection />
    <CtaSection />
    <LandingFooter />
  </main>
</template>
