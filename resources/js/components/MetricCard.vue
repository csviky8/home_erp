<script setup>
import { ArrowUpRight } from 'lucide-vue-next';
defineProps({ label: String, value: [String, Number], detail: String, tone: { type: String, default: 'mint' }, icon: Object });
</script>

<template>
    <div class="panel panel-hover p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="eyebrow truncate">{{ label }}</p>
                <p class="mt-3 text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ value }}</p>
                <p v-if="detail" class="mt-1 text-xs text-[#86ada3]">{{ detail }}</p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="tone === 'amber' ? 'bg-amber-400/10 text-amber-200' : tone === 'blue' ? 'bg-sky-400/10 text-sky-200' : 'bg-emerald-300/10 text-emerald-200'">
                <component :is="icon" :size="18" />
            </div>
        </div>
        <div class="mt-4 flex items-center gap-1 text-[11px] text-[#79cbae]"><ArrowUpRight :size="13" /> Live household data</div>
    </div>
</template>
