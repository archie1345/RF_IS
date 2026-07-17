<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import { index as parentChildrenIndex } from '@/routes/parent/children';
</script>

<template>
    <PageSection title="Child account settings" description="Open a linked child profile to update account details, athlete data, achievements, certifications, and password.">
        <Button as-child variant="outline">
            <Link :href="parentChildrenIndex.url()">Manage Child Profiles</Link>
        </Button>
    </PageSection>
</template>

