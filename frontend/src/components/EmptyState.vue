<template><div class="panel empty-state"><component :is="icon || Inbox" aria-hidden="true" /><h2>{{ title }}</h2><p>{{ description }}</p><slot /></div></template>
<script setup lang="ts">
import { Inbox } from 'lucide-vue-next'
import type { Component } from 'vue'
defineProps<{ title: string; description: string; icon?: Component }>()
</script>
