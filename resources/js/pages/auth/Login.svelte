<script module lang="ts">
    export const layout = {
        title: 'Masuk ke Portal Kepegawaian',
        description: 'Silakan masukkan kredensial akun Anda untuk mengakses sistem presensi & kepegawaian.',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import KeyRound from '@lucide/svelte/icons/key-round';
    import Lock from '@lucide/svelte/icons/lock';
    import Mail from '@lucide/svelte/icons/mail';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import User from '@lucide/svelte/icons/user';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasskeyVerify from '@/components/PasskeyVerify.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { store } from '@/routes/login';

    let {
        status = '',
        showDemoAccounts = true,
    }: {
        status?: string;
        showDemoAccounts?: boolean;
    } = $props();

    let emailValue = $state('');
    let passwordValue = $state('');

    function fillDemoAccount(email: string) {
        emailValue = email;
        passwordValue = 'password';
    }
</script>

<AppHead title="Masuk ke Sistem Kehadiran" />

{#if status}
    <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 p-3 text-center text-sm font-medium text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
        {status}
    </div>
{/if}

<PasskeyVerify />

<Form
    {...store.form()}
    resetOnSuccess={['password']}
    class="flex flex-col gap-5"
>
    {#snippet children({ errors, processing })}
        <div class="space-y-4">
            <!-- Email Input -->
            <div class="space-y-1.5">
                <Label for="email" class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                    <Mail class="size-3.5 text-muted-foreground" />
                    <span>Alamat Email Pegawai</span>
                </Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="nama.pegawai@instansi.go.id"
                    bind:value={emailValue}
                    class="h-10"
                />
                <InputError message={errors.email} />
            </div>

            <!-- Password Input -->
            <div class="space-y-1.5">
                <Label for="password" class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                    <Lock class="size-3.5 text-muted-foreground" />
                    <span>Kata Sandi</span>
                </Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    bind:value={passwordValue}
                    class="h-10"
                />
                <InputError message={errors.password} />
            </div>

            <!-- Remember me -->
            <div class="flex items-center justify-between pt-1">
                <Label for="remember" class="flex items-center space-x-2.5 text-xs text-muted-foreground cursor-pointer select-none">
                    <Checkbox id="remember" name="remember" />
                    <span>Ingat perangkat ini</span>
                </Label>
            </div>

            <!-- Submit Button -->
            <Button
                type="submit"
                class="mt-2 w-full h-10 font-semibold shadow-md transition-all hover:shadow-lg"
                disabled={processing}
                data-test="login-button"
            >
                {#if processing}
                    <Spinner class="mr-2 size-4" />
                    <span>Memverifikasi...</span>
                {:else}
                    <KeyRound class="mr-2 size-4" />
                    <span>Masuk ke Portal</span>
                {/if}
            </Button>
        </div>

        <!-- Quick Demo Switcher (Only in non-production environments) -->
        {#if showDemoAccounts}
            <div class="mt-4 pt-4 border-t border-border/70 space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                        Akses Cepat Pengujian:
                    </span>
                    <span class="text-[11px] text-muted-foreground font-mono">pwd: password</span>
                </div>

                <div class="grid grid-cols-3 gap-1.5 text-xs">
                    <button
                        type="button"
                        onclick={() => fillDemoAccount('admin@example.com')}
                        class="flex flex-col items-center justify-center p-2 rounded-lg border border-border/80 bg-background hover:bg-muted/80 hover:border-primary/50 transition-all text-center group cursor-pointer"
                        title="Login sebagai Super Admin"
                    >
                        <div class="flex items-center gap-1 font-semibold text-foreground group-hover:text-primary text-[11px]">
                            <ShieldCheck class="size-3 text-amber-500" />
                            <span>Super Admin</span>
                        </div>
                        <span class="text-[10px] text-muted-foreground truncate w-full mt-0.5">admin@...</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => fillDemoAccount('ahmad@example.com')}
                        class="flex flex-col items-center justify-center p-2 rounded-lg border border-border/80 bg-background hover:bg-muted/80 hover:border-primary/50 transition-all text-center group cursor-pointer"
                        title="Login sebagai Admin Bagian SDM"
                    >
                        <div class="flex items-center gap-1 font-semibold text-foreground group-hover:text-primary text-[11px]">
                            <ShieldCheck class="size-3 text-blue-500" />
                            <span>Admin Bagian</span>
                        </div>
                        <span class="text-[10px] text-muted-foreground truncate w-full mt-0.5">ahmad@...</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => fillDemoAccount('budi@example.com')}
                        class="flex flex-col items-center justify-center p-2 rounded-lg border border-border/80 bg-background hover:bg-muted/80 hover:border-primary/50 transition-all text-center group cursor-pointer"
                        title="Login sebagai Staf Pegawai TI"
                    >
                        <div class="flex items-center gap-1 font-semibold text-foreground group-hover:text-primary text-[11px]">
                            <User class="size-3 text-emerald-500" />
                            <span>Pegawai</span>
                        </div>
                        <span class="text-[10px] text-muted-foreground truncate w-full mt-0.5">budi@...</span>
                    </button>
                </div>
            </div>
        {/if}
    {/snippet}
</Form>
