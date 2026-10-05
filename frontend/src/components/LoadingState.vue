<template><div role="status" aria-live="polite"><span class="sr-only">{{ label || 'Loading workspace data' }}</span><div class="task-grid" aria-hidden="true"><div v-for="i in count || 3" :key="i" class="skeleton" /></div></div></template>
<script setup lang="ts">defineProps<{ label?: string; count?: number }>()</script>
