<script setup lang="ts">
const responseActions = defineModel<string | null>('responseActions', { required: true });
</script>

<template>
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-slate-900">XX. Response Actions</h3>
        <textarea
            :value="responseActions ?? ''"
            rows="6"
            maxlength="5000"
            class="block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
            placeholder="Describe response actions taken..."
            @input="responseActions = ($event.target as HTMLTextAreaElement).value || null"
        ></textarea>
        <p class="text-xs text-slate-500">{{ (responseActions ?? '').length }}/5000 characters</p>
    </div>
</template>
