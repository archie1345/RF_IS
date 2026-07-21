<script setup lang="ts">
import type { SkeletonProps } from './Skeleton.types';
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"


const props = defineProps<SkeletonProps>()
</script>

<template>
  <div
    data-slot="skeleton"
    :class="cn('animate-pulse rounded-md bg-primary/10', props.class)"
  />
</template>
