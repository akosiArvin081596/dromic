<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Welcome" />
    <div class="flex min-h-screen flex-col items-center justify-center bg-indigo-900 px-4">
        <div class="text-center">
            <h1 class="text-5xl font-bold tracking-tight text-white sm:text-6xl">DROMIC-IS</h1>
            <p class="mt-4 text-lg text-indigo-200">Disaster Response Operations Monitoring and Information Center</p>
            <p class="mt-1 text-sm text-indigo-300">Information System</p>
            <div class="mt-10">
                <Link href="/login" class="bg-white px-8 py-3 text-sm font-semibold text-indigo-900 transition-colors hover:bg-indigo-50">
                    Sign In
                </Link>
            </div>
            <p class="mt-16 text-xs text-indigo-400">DSWD Field Office Caraga</p>
        </div>
    </div>
</template>
