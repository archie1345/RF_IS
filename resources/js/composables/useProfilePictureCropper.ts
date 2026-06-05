import { onBeforeUnmount, ref } from 'vue';

type CropperResult = {
    canvas?: HTMLCanvasElement;
};

type CropperInstance = {
    getResult?: () => CropperResult;
    zoom?: (factor: number) => void;
    rotate?: (angle: number) => void;
    reset?: () => void;
};

type UseProfilePictureCropperOptions = {
    width?: number;
    height?: number;
    maxSizeBytes?: number;
    onCroppedFileChange?: (file: File | null) => void;
};

export function useProfilePictureCropper(options: UseProfilePictureCropperOptions = {}) {
    const profilePictureWidth = options.width ?? 600;
    const profilePictureHeight = options.height ?? 800;
    const maxSizeBytes = options.maxSizeBytes ?? 2 * 1024 * 1024;
    const selectedImage = ref<string | null>(null);
    const selectedImageObjectUrl = ref<string | null>(null);
    const cropperRef = ref<CropperInstance | null>(null);
    const profilePictureError = ref('');
    const profilePictureReady = ref(false);
    const profilePictureFileInput = ref<HTMLInputElement | null>(null);

    function setCroppedFile(file: File | null) {
        options.onCroppedFileChange?.(file);
    }

    function markCropDirty() {
        profilePictureReady.value = false;
        setCroppedFile(null);
    }

    function revokeSelectedImageObjectUrl() {
        if (selectedImageObjectUrl.value) {
            URL.revokeObjectURL(selectedImageObjectUrl.value);
            selectedImageObjectUrl.value = null;
        }
    }

    function clearSelectedImage() {
        revokeSelectedImageObjectUrl();
        selectedImage.value = null;
        profilePictureError.value = '';
        markCropDirty();

        if (profilePictureFileInput.value) {
            profilePictureFileInput.value.value = '';
        }
    }

    async function onProfilePictureChange(event: Event) {
        const target = event.target as HTMLInputElement;
        const file = target.files?.[0];

        if (!file) return;

        if (file.size > maxSizeBytes) {
            profilePictureError.value = 'Profile picture must be smaller than 2MB.';
            target.value = '';
            return;
        }

        if (!file.type.startsWith('image/')) {
            profilePictureError.value = 'Selected file must be an image.';
            target.value = '';
            return;
        }

        profilePictureError.value = '';
        markCropDirty();

        revokeSelectedImageObjectUrl();
        selectedImageObjectUrl.value = URL.createObjectURL(file);
        selectedImage.value = selectedImageObjectUrl.value;
    }

    function editCurrentProfilePicture(profilePictureUrl?: string | null) {
        if (!profilePictureUrl) return;

        revokeSelectedImageObjectUrl();
        selectedImage.value = profilePictureUrl;
        profilePictureError.value = '';
        markCropDirty();
    }

    async function applyCrop(): Promise<File | null> {
        const canvas = cropperRef.value?.getResult?.()?.canvas;

        if (!canvas) {
            profilePictureError.value = 'Move or zoom the image, then apply the 3x4 crop.';
            return null;
        }

        const outputCanvas = document.createElement('canvas');
        outputCanvas.width = profilePictureWidth;
        outputCanvas.height = profilePictureHeight;

        const context = outputCanvas.getContext('2d');

        if (!context) {
            profilePictureError.value = 'Could not prepare the cropped image.';
            return null;
        }

        context.drawImage(canvas, 0, 0, profilePictureWidth, profilePictureHeight);

        const croppedFile = await new Promise<File | null>((resolve) => {
            outputCanvas.toBlob(
                (blob: Blob | null) => {
                    if (!blob) {
                        resolve(null);
                        return;
                    }

                    resolve(new File([blob], 'profile-picture-3x4.jpg', { type: 'image/jpeg' }));
                },
                'image/jpeg',
                0.9,
            );
        });

        if (!croppedFile) {
            profilePictureError.value = 'Could not prepare the cropped image.';
            return null;
        }

        setCroppedFile(croppedFile);
        profilePictureReady.value = true;
        profilePictureError.value = '';

        return croppedFile;
    }

    function zoomCrop(factor: number) {
        cropperRef.value?.zoom?.(factor);
        markCropDirty();
    }

    function rotateCrop() {
        cropperRef.value?.rotate?.(90);
        markCropDirty();
    }

    function resetCrop() {
        cropperRef.value?.reset?.();
        markCropDirty();
    }

    onBeforeUnmount(() => {
        revokeSelectedImageObjectUrl();
    });

    return {
        selectedImage,
        cropperRef,
        profilePictureError,
        profilePictureReady,
        profilePictureFileInput,
        profilePictureWidth,
        profilePictureHeight,
        clearSelectedImage,
        onProfilePictureChange,
        editCurrentProfilePicture,
        applyCrop,
        zoomCrop,
        rotateCrop,
        resetCrop,
        markCropDirty,
    };
}
