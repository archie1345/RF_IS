<script setup lang="ts">
import PageSection from '@/components/mvp/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <PageSection title="Child account settings" description="Parents can manage child context here and update password from settings.">
        <Button as-child variant="outline">
            <Link href="/settings/password">Edit password</Link>
        </Button>
    </PageSection>
</template>
