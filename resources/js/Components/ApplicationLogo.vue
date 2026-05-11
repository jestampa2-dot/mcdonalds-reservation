<script setup>
import brandLogoCircle from '@/assets/brand-logo-circle.png'
</script>

<template>
    <span class="application-logo" aria-hidden="true">
        <img
            :src="brandLogoCircle"
            alt="McDonald's Reservations logo"
            class="application-logo__image"
            loading="eager"
            decoding="async"
        />
    </span>
</template>

<style scoped>
.application-logo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.application-logo__image {
    display: block;
    width: 84%;
    height: 84%;
    object-fit: contain;
}
</style>
