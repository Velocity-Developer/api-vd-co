<script setup lang="ts">
import { computed, ref, watch } from 'vue';

/**
 * Upload field for a single picture that may already be saved: shows the
 * saved picture with Replace/Remove, or a drop zone for a new file.
 */
const props = withDefaults(
    defineProps<{
        /** URL of the saved picture, or null when there is none. */
        currentUrl: string | null;
        /** Used in the alt text, e.g. "seller". */
        noun: string;
        disabled?: boolean;
        /** Wide preview for banners instead of a square thumbnail. */
        wide?: boolean;
    }>(),
    { disabled: false, wide: false },
);

const file = defineModel<File | null>('file', { default: null });
const remove = defineModel<boolean>('remove', { default: false });

const imageAccept = '.jpg,.jpeg,.png,.webp,.gif,.avif';
const isReplacing = ref(false);

const showsCurrentImage = computed(
    () => props.currentUrl !== null && !remove.value && !isReplacing.value,
);

const keepCurrentImage = (): void => {
    file.value = null;
    remove.value = false;
    isReplacing.value = false;
};

watch(
    () => props.currentUrl,
    () => {
        isReplacing.value = false;
    },
);
</script>

<template>
    <div>
        <div
            v-if="showsCurrentImage"
            class="flex items-center gap-3 rounded-md border border-default p-2"
        >
            <img
                :src="currentUrl ?? undefined"
                :alt="`Gambar ${noun} saat ini`"
                class="shrink-0 rounded object-cover"
                :class="wide ? 'h-16 w-48' : 'size-16'"
            />
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <p class="text-sm text-muted">Gambar saat ini</p>
                <div class="flex gap-1">
                    <UButton
                        label="Ganti"
                        icon="i-lucide-replace"
                        color="neutral"
                        variant="outline"
                        size="xs"
                        :disabled="disabled"
                        @click="isReplacing = true"
                    />
                    <UButton
                        label="Hapus"
                        icon="i-lucide-trash"
                        color="error"
                        variant="ghost"
                        size="xs"
                        :disabled="disabled"
                        @click="remove = true"
                    />
                </div>
            </div>
        </div>

        <template v-else>
            <UFileUpload
                v-model="file"
                :accept="imageAccept"
                icon="i-lucide-image-up"
                label="Tarik gambar ke sini"
                description="atau klik untuk memilih"
                :disabled="disabled"
                class="min-h-32 w-full"
            />
            <UButton
                v-if="currentUrl && (remove || isReplacing)"
                :label="
                    remove
                        ? 'Batalkan hapus gambar'
                        : 'Batal, pakai gambar lama'
                "
                icon="i-lucide-undo-2"
                color="neutral"
                variant="link"
                size="xs"
                class="mt-1 px-0"
                :disabled="disabled"
                @click="keepCurrentImage"
            />
        </template>
    </div>
</template>
