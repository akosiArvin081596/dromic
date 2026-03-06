import { ref, watch } from 'vue';

const isDark = ref(false);

function applyTheme(dark: boolean): void {
    document.documentElement.classList.toggle('dark', dark);
    localStorage.setItem('theme', dark ? 'dark' : 'light');
}

export function useDarkMode() {
    // Initialize from localStorage or system preference (only once)
    const stored = localStorage.getItem('theme');
    if (stored) {
        isDark.value = stored === 'dark';
    } else {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    applyTheme(isDark.value);

    watch(isDark, (dark) => applyTheme(dark));

    function toggle(): void {
        isDark.value = !isDark.value;
    }

    return { isDark, toggle };
}
