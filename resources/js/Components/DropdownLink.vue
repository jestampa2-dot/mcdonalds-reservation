<script setup>
import { computed, useAttrs } from 'vue';
import { Link } from '@inertiajs/vue3';

const attrs = useAttrs();
const shouldPrefetch = computed(() => !attrs.method || String(attrs.method).toLowerCase() === 'get');
</script>

<template>
    <Link
        :prefetch="shouldPrefetch"
        class="block w-full px-4 py-2 text-left text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
    >
        <slot />
    </Link>
</template>
