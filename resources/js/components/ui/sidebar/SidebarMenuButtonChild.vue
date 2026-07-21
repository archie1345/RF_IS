<script setup lang="ts">
import type { SidebarMenuButtonProps } from './SidebarMenuButtonChild.types';
import type { PrimitiveProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { SidebarMenuButtonVariants } from "."
import { Primitive } from "reka-ui"
import { cn } from "@/lib/utils"
import { sidebarMenuButtonVariants } from "."


const props = withDefaults(defineProps<SidebarMenuButtonProps>(), {
  as: "button",
  variant: "default",
  size: "default",
})
</script>

<template>
  <Primitive
    data-slot="sidebar-menu-button"
    data-sidebar="menu-button"
    :data-size="props.size"
    :data-active="props.isActive"
    :class="cn(sidebarMenuButtonVariants({ variant: props.variant, size: props.size }), props.class)"
    :as="props.as"
    :as-child="props.asChild"
    v-bind="$attrs"
  >
    <slot />
  </Primitive>
</template>
