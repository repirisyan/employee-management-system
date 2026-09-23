import { router } from '@inertiajs/svelte';
import { toast } from 'svelte-sonner';
import type { FlashToast } from '@/types/ui';

let lastFlashTime = 0;

function handleFlashObject(flash: any): void {
    if (!flash) return;

    const data = flash.toast as FlashToast | undefined;

    if (data?.message) {
        const type = data.type;
        if (type === 'success') {
            toast.success(data.message);
        } else if (type === 'info') {
            toast.info(data.message);
        } else if (type === 'warning') {
            toast.warning(data.message);
        } else if (type === 'error') {
            toast.error(data.message);
        } else {
            toast(data.message);
        }
        return;
    }

    // Handle direct flash props if sent directly
    if (flash.success) {
        toast.success(flash.success);
    } else if (flash.info) {
        toast.info(flash.info);
    } else if (flash.warning) {
        toast.warning(flash.warning);
    } else if (flash.error) {
        toast.error(flash.error);
    } else if (flash.status) {
        toast.info(flash.status);
    }
}

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        if (flash) {
            lastFlashTime = Date.now();
            handleFlashObject(flash);
        }
    });

    router.on('success', (event) => {
        if (Date.now() - lastFlashTime < 150) return;
        const page = (event as any).detail?.page;
        const flash = page?.props?.flash;
        if (flash) {
            handleFlashObject(flash);
        }
    });
}

/**
 * Direct client-side toast helper
 */
export const notify = {
    success: (message: string, description?: string) =>
        toast.success(message, description ? { description } : undefined),
    info: (message: string, description?: string) =>
        toast.info(message, description ? { description } : undefined),
    warning: (message: string, description?: string) =>
        toast.warning(message, description ? { description } : undefined),
    error: (message: string, description?: string) =>
        toast.error(message, description ? { description } : undefined),
};

