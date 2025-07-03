<script setup>
import ApplicationMark from '@/Components/ApplicationMark.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    title: String,
});
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">

        <div class="w-full sm:max-w-7xl mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg mx-auto">
            <slot />
        </div>

    </div>
</template>