<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { EyeIcon, EyeOffIcon, LockIcon, MailIcon, Moon, Sun } from 'lucide-vue-next';
import { ref } from 'vue';
import logoImage from '@/../../resources/images/dswd-caraga-logo.jpg';
import { useDarkMode } from '@/composables/useDarkMode';

const { isDark, toggle: toggleDarkMode } = useDarkMode();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Sign In" />

    <div class="grid h-dvh lg:grid-cols-2">
        <!-- Left panel — branding (desktop only) -->
        <div
            class="sticky top-0 hidden h-dvh flex-col items-center justify-center bg-gradient-to-br from-blue-600 via-blue-700 to-blue-800 px-12 lg:flex dark:from-blue-700 dark:via-blue-800 dark:to-blue-900"
        >
            <div class="flex animate-fade-in-up flex-col items-center text-center">
                <div class="mb-6 h-28 w-28 overflow-hidden rounded-full border-4 border-white/20 bg-white shadow-2xl">
                    <img :src="logoImage" alt="DSWD Caraga Logo" class="h-full w-full object-cover" />
                </div>
                <h1 class="text-3xl font-extrabold tracking-[0.25em] text-white uppercase">DROMIC REPORTING</h1>
                <div class="mx-auto mt-4 h-0.5 w-16 bg-white/30"></div>
                <p class="mt-4 max-w-xs text-sm leading-relaxed text-blue-100/80">
                    Disaster Response Operations Management, Information and Communication
                </p>
            </div>
        </div>

        <!-- Right panel — login form -->
        <div class="relative flex flex-col bg-white dark:bg-slate-900">
            <button
                class="absolute top-4 right-4 z-10 rounded-full p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                title="Toggle dark mode"
                @click="toggleDarkMode"
            >
                <Moon v-if="!isDark" class="h-5 w-5" />
                <Sun v-else class="h-5 w-5" />
            </button>
            <div class="flex flex-1 flex-col items-center justify-center px-6 py-10 sm:px-12 lg:px-16">
                <div class="w-full max-w-lg animate-fade-in-up">
                    <!-- Mobile header (hidden on desktop) -->
                    <div class="mb-8 flex flex-col items-center text-center lg:hidden">
                        <div
                            class="mb-4 h-20 w-20 overflow-hidden rounded-full border-4 border-blue-600/20 bg-white shadow-lg dark:border-blue-400/20"
                        >
                            <img :src="logoImage" alt="DSWD Caraga Logo" class="h-full w-full object-cover" />
                        </div>
                        <h1 class="text-base font-extrabold tracking-[0.2em] text-blue-700 uppercase dark:text-blue-400">DROMIC REPORTING</h1>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submit">
                        <h2 class="mb-1 text-2xl font-bold text-slate-900 dark:text-white">Welcome back</h2>
                        <p class="mb-8 text-base text-slate-500 dark:text-slate-400">Sign in to your account to continue</p>

                        <!-- Email field -->
                        <div class="mb-6">
                            <label for="email" class="mb-2 block text-sm font-semibold tracking-wider text-slate-600 uppercase dark:text-slate-400">
                                Email Address
                            </label>
                            <div class="relative">
                                <span
                                    class="pointer-events-none absolute top-0 left-0 flex h-12 w-12 items-center justify-center border-r border-slate-200 text-slate-400 dark:border-slate-700"
                                >
                                    <MailIcon class="h-5 w-5" />
                                </span>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autofocus
                                    :tabindex="1"
                                    autocomplete="email"
                                    placeholder="you@example.com"
                                    class="h-12 w-full rounded-lg border bg-slate-50 pr-4 pl-15 text-base text-slate-900 transition-all focus:bg-white focus:ring-2 focus:outline-none dark:bg-slate-800 dark:text-white dark:focus:bg-slate-800"
                                    :class="
                                        form.errors.email
                                            ? 'border-red-400 focus:border-red-500 focus:ring-red-200 dark:border-red-600 dark:focus:ring-red-800'
                                            : 'border-slate-200 focus:border-blue-500 focus:ring-blue-200 dark:border-slate-700 dark:focus:border-blue-400 dark:focus:ring-blue-800'
                                    "
                                />
                            </div>
                            <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-600 dark:text-red-500">{{ form.errors.email }}</p>
                        </div>

                        <!-- Password field -->
                        <div class="mb-6">
                            <label
                                for="password"
                                class="mb-2 block text-sm font-semibold tracking-wider text-slate-600 uppercase dark:text-slate-400"
                            >
                                Password
                            </label>
                            <div class="relative">
                                <span
                                    class="pointer-events-none absolute top-0 left-0 flex h-12 w-12 items-center justify-center border-r border-slate-200 text-slate-400 dark:border-slate-700"
                                >
                                    <LockIcon class="h-5 w-5" />
                                </span>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    :tabindex="2"
                                    autocomplete="current-password"
                                    placeholder="Enter your password"
                                    class="h-12 w-full rounded-lg border bg-slate-50 pr-12 pl-15 text-base text-slate-900 transition-all focus:bg-white focus:ring-2 focus:outline-none dark:bg-slate-800 dark:text-white dark:focus:bg-slate-800"
                                    :class="
                                        form.errors.password
                                            ? 'border-red-400 focus:border-red-500 focus:ring-red-200 dark:border-red-600 dark:focus:ring-red-800'
                                            : 'border-slate-200 focus:border-blue-500 focus:ring-blue-200 dark:border-slate-700 dark:focus:border-blue-400 dark:focus:ring-blue-800'
                                    "
                                />
                                <button
                                    type="button"
                                    :tabindex="5"
                                    class="absolute top-0 right-0 flex h-12 w-12 items-center justify-center text-slate-400 transition-colors hover:text-slate-600 dark:hover:text-slate-300"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOffIcon v-if="showPassword" class="h-5 w-5" />
                                    <EyeIcon v-else class="h-5 w-5" />
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1.5 text-sm text-red-600 dark:text-red-500">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- Remember me -->
                        <div class="mb-8 flex items-center">
                            <label for="remember" class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600 dark:text-slate-400">
                                <input
                                    id="remember"
                                    v-model="form.remember"
                                    type="checkbox"
                                    :tabindex="3"
                                    class="h-4.5 w-4.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                                Remember me
                            </label>
                        </div>

                        <!-- Submit button -->
                        <button
                            type="submit"
                            :tabindex="4"
                            :disabled="form.processing"
                            class="relative flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 text-sm font-semibold tracking-widest text-white uppercase shadow-md transition-all hover:bg-blue-700 hover:shadow-lg focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50 dark:focus:ring-offset-slate-900"
                        >
                            <svg
                                v-if="form.processing"
                                class="h-5 w-5 animate-spin text-white"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ form.processing ? 'Signing in...' : 'Sign In' }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="py-5 text-center">
                <div class="mx-auto mb-2 h-px w-16 bg-slate-200 dark:bg-slate-700"></div>
                <p class="text-xs text-slate-400 dark:text-slate-500">DSWD Field Office Caraga</p>
            </div>
        </div>
    </div>
</template>
