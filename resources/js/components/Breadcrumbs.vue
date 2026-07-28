<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { Props } from './Breadcrumbs.types';

defineProps<Props>();
</script>

<template>
    <Breadcrumb class="min-w-0 overflow-hidden">
        <BreadcrumbList class="min-w-0 flex-nowrap overflow-hidden">
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem
                    :class="index === breadcrumbs.length - 1 ? 'min-w-0' : 'hidden shrink-0 sm:inline-flex'"
                >
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage class="block max-w-[42vw] truncate sm:max-w-[55vw] lg:max-w-none">
                            {{ item.title }}
                        </BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link :href="item.href ?? '#'">{{ item.title }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" class="hidden shrink-0 sm:inline-flex" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
