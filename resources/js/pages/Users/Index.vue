<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { User } from '@/types/auth';

type Role = { value: string; label: string };
type Region = { id: number; name: string };
type Province = { id: number; name: string; region_id: number; region?: Region };
type CityMunicipality = { id: number; name: string; province_id: number; province?: Province };

const props = defineProps<{
    users: {
        data: (User & { region?: Region; province?: Province; city_municipality?: CityMunicipality })[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
    };
    filters: { search?: string; role?: string };
    userCounts: { total: number; admin: number; regional: number; provincial: number; lgu: number };
    regions: Region[];
    provinces: Province[];
    cityMunicipalities: CityMunicipality[];
    roles: Role[];
}>();

const search = ref(props.filters.search ?? '');
const roleFilter = ref(props.filters.role ?? '');
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteConfirm = ref(false);
const userToDelete = ref<User | null>(null);

let debounceTimer: ReturnType<typeof setTimeout>;
watch([search, roleFilter], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/users', { search: search.value || undefined, role: roleFilter.value || undefined }, { preserveState: true, replace: true });
    }, 300);
});

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'lgu',
    region_id: null as number | null,
    province_id: null as number | null,
    city_municipality_id: null as number | null,
});

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'lgu',
    region_id: null as number | null,
    province_id: null as number | null,
    city_municipality_id: null as number | null,
});

const editingUser = ref<User | null>(null);

function openCreate() {
    createForm.reset();
    createForm.clearErrors();
    showCreateModal.value = true;
}

function submitCreate() {
    createForm.post('/users', {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
}

function openEdit(user: User & { region?: Region; province?: Province; city_municipality?: CityMunicipality }) {
    editingUser.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.role = user.role;
    editForm.region_id = user.region_id;
    editForm.province_id = user.province_id;
    editForm.city_municipality_id = user.city_municipality_id;
    editForm.clearErrors();
    showEditModal.value = true;
}

function submitEdit() {
    if (!editingUser.value) return;
    editForm.put(`/users/${editingUser.value.id}`, {
        onSuccess: () => {
            showEditModal.value = false;
            editingUser.value = null;
        },
    });
}

function confirmDelete(user: User) {
    userToDelete.value = user;
    showDeleteConfirm.value = true;
}

function deleteUser() {
    if (!userToDelete.value) return;
    router.delete(`/users/${userToDelete.value.id}`, {
        onSuccess: () => {
            showDeleteConfirm.value = false;
            userToDelete.value = null;
        },
    });
}

const filteredProvincesForCreate = computed(() =>
    createForm.region_id ? props.provinces.filter((p) => p.region_id === createForm.region_id) : props.provinces,
);

const filteredCitiesForCreate = computed(() =>
    createForm.province_id ? props.cityMunicipalities.filter((c) => c.province_id === createForm.province_id) : props.cityMunicipalities,
);

const filteredProvincesForEdit = computed(() =>
    editForm.region_id ? props.provinces.filter((p) => p.region_id === editForm.region_id) : props.provinces,
);

const filteredCitiesForEdit = computed(() =>
    editForm.province_id ? props.cityMunicipalities.filter((c) => c.province_id === editForm.province_id) : props.cityMunicipalities,
);

function roleBadgeClass(role: string): string {
    switch (role) {
        case 'admin':
            return 'bg-violet-50 text-violet-700 ring-1 ring-violet-600/20';
        case 'regional':
            return 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20';
        case 'provincial':
            return 'bg-sky-50 text-sky-700 ring-1 ring-sky-600/20';
        case 'lgu':
            return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20';
        case 'regional_director':
            return 'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20';
        case 'escort':
            return 'bg-slate-50 text-slate-700 ring-1 ring-slate-600/20';
        default:
            return 'bg-slate-50 text-slate-700 ring-1 ring-slate-600/20';
    }
}

function locationLabel(user: User & { region?: Region; province?: Province; city_municipality?: CityMunicipality }): string {
    if (user.city_municipality) {
        const prov = user.city_municipality.province?.name ?? '';
        return `${user.city_municipality.name}${prov ? ', ' + prov : ''}`;
    }
    if (user.province) return user.province.name;
    if (user.region) return user.region.name;
    return '-';
}
</script>

<template>
    <AppLayout>
        <Head title="User Management" />
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">User Management</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage system user accounts and roles</p>
                </div>
                <button
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700"
                    @click="openCreate"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add User
                </button>
            </div>

            <!-- Stat Cards -->
            <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-5">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Total</span>
                    <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ userCounts.total }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-xs font-medium tracking-wide text-violet-500 uppercase">Admin</span>
                    <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ userCounts.admin }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-xs font-medium tracking-wide text-amber-500 uppercase">Regional</span>
                    <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ userCounts.regional }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-xs font-medium tracking-wide text-sky-500 uppercase">Provincial</span>
                    <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ userCounts.provincial }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-xs font-medium tracking-wide text-emerald-500 uppercase">LGU</span>
                    <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ userCounts.lgu }}</div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-sm font-semibold tracking-wide text-slate-900 uppercase dark:text-slate-100">All Users</h2>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <div class="relative">
                                <svg
                                    class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
                                    />
                                </svg>
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search name or email..."
                                    class="block w-full rounded-lg border border-slate-300 py-2 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:w-64 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                />
                            </div>
                            <select
                                v-model="roleFilter"
                                class="block rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option value="">All Roles</option>
                                <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50/50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">Location</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold tracking-wider text-slate-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-800">
                            <tr v-for="user in users.data" :key="user.id" class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4 text-sm font-medium whitespace-nowrap text-slate-900 dark:text-slate-100">{{ user.name }}</td>
                                <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400">{{ user.email }}</td>
                                <td class="px-6 py-4 text-sm whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="roleBadgeClass(user.role)"
                                    >
                                        {{ user.role.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400">{{ locationLabel(user) }}</td>
                                <td class="px-6 py-4 text-right text-sm whitespace-nowrap">
                                    <button class="text-indigo-600 transition-colors hover:text-indigo-800" @click="openEdit(user)">Edit</button>
                                    <button class="ml-3 text-red-600 transition-colors hover:text-red-800" @click="confirmDelete(user)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-400">No users found</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="users.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-6 py-3 dark:border-slate-700">
                    <p class="text-xs text-slate-500">Page {{ users.current_page }} of {{ users.last_page }}</p>
                    <div class="flex space-x-1">
                        <template v-for="link in users.links" :key="link.label">
                            <a
                                v-if="link.url"
                                :href="link.url"
                                class="rounded-lg px-3 py-1.5 text-sm transition-colors"
                                :class="
                                    link.active
                                        ? 'bg-indigo-600 text-white shadow-sm'
                                        : 'bg-white text-slate-700 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300'
                                "
                                @click.prevent="router.get(link.url!, {}, { preserveState: true })"
                            >
                                <span v-html="link.label" />
                            </a>
                            <span v-else class="rounded-lg px-3 py-1.5 text-sm text-slate-300">
                                <span v-html="link.label" />
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showCreateModal = false">
                <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <h3 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Add User</h3>
                    <form class="space-y-4" @submit.prevent="submitCreate">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                            <input
                                v-model="createForm.name"
                                type="text"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            />
                            <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-600">{{ createForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                            <input
                                v-model="createForm.email"
                                type="email"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            />
                            <p v-if="createForm.errors.email" class="mt-1 text-xs text-red-600">{{ createForm.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
                            <input
                                v-model="createForm.password"
                                type="password"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            />
                            <p v-if="createForm.errors.password" class="mt-1 text-xs text-red-600">{{ createForm.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Role</label>
                            <select
                                v-model="createForm.role"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                            </select>
                        </div>
                        <div v-if="createForm.role === 'regional' || createForm.role === 'regional_director'">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Region</label>
                            <select
                                v-model="createForm.region_id"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option :value="null">Select region</option>
                                <option v-for="region in regions" :key="region.id" :value="region.id">{{ region.name }}</option>
                            </select>
                        </div>
                        <div v-if="createForm.role === 'provincial'">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Province</label>
                            <select
                                v-model="createForm.province_id"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option :value="null">Select province</option>
                                <option v-for="province in filteredProvincesForCreate" :key="province.id" :value="province.id">
                                    {{ province.name }}
                                </option>
                            </select>
                        </div>
                        <div v-if="createForm.role === 'lgu'">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Province (filter)</label>
                            <select
                                v-model="createForm.province_id"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option :value="null">All provinces</option>
                                <option v-for="province in provinces" :key="province.id" :value="province.id">{{ province.name }}</option>
                            </select>
                            <label class="mt-3 block text-sm font-medium text-slate-700 dark:text-slate-300">City/Municipality</label>
                            <select
                                v-model="createForm.city_municipality_id"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option :value="null">Select city/municipality</option>
                                <option v-for="city in filteredCitiesForCreate" :key="city.id" :value="city.id">{{ city.name }}</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                                @click="showCreateModal = false"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                                :disabled="createForm.processing"
                            >
                                {{ createForm.processing ? 'Creating...' : 'Create User' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Edit Modal -->
        <Teleport to="body">
            <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showEditModal = false">
                <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <h3 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Edit User</h3>
                    <form class="space-y-4" @submit.prevent="submitEdit">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                            <input
                                v-model="editForm.name"
                                type="text"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            />
                            <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                            <input
                                v-model="editForm.email"
                                type="email"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            />
                            <p v-if="editForm.errors.email" class="mt-1 text-xs text-red-600">{{ editForm.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                                >Password <span class="text-slate-400">(leave blank to keep current)</span></label
                            >
                            <input
                                v-model="editForm.password"
                                type="password"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            />
                            <p v-if="editForm.errors.password" class="mt-1 text-xs text-red-600">{{ editForm.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Role</label>
                            <select
                                v-model="editForm.role"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                            </select>
                        </div>
                        <div v-if="editForm.role === 'regional' || editForm.role === 'regional_director'">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Region</label>
                            <select
                                v-model="editForm.region_id"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option :value="null">Select region</option>
                                <option v-for="region in regions" :key="region.id" :value="region.id">{{ region.name }}</option>
                            </select>
                        </div>
                        <div v-if="editForm.role === 'provincial'">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Province</label>
                            <select
                                v-model="editForm.province_id"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option :value="null">Select province</option>
                                <option v-for="province in filteredProvincesForEdit" :key="province.id" :value="province.id">
                                    {{ province.name }}
                                </option>
                            </select>
                        </div>
                        <div v-if="editForm.role === 'lgu'">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Province (filter)</label>
                            <select
                                v-model="editForm.province_id"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option :value="null">All provinces</option>
                                <option v-for="province in provinces" :key="province.id" :value="province.id">{{ province.name }}</option>
                            </select>
                            <label class="mt-3 block text-sm font-medium text-slate-700 dark:text-slate-300">City/Municipality</label>
                            <select
                                v-model="editForm.city_municipality_id"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option :value="null">Select city/municipality</option>
                                <option v-for="city in filteredCitiesForEdit" :key="city.id" :value="city.id">{{ city.name }}</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                                @click="showEditModal = false"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                                :disabled="editForm.processing"
                            >
                                {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Delete Confirmation -->
        <Teleport to="body">
            <div
                v-if="showDeleteConfirm"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.self="showDeleteConfirm = false"
            >
                <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Delete User</h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Are you sure you want to delete <strong>{{ userToDelete?.name }}</strong
                        >? This action cannot be undone.
                    </p>
                    <div class="mt-4 flex justify-end gap-3">
                        <button
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                            @click="showDeleteConfirm = false"
                        >
                            Cancel
                        </button>
                        <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" @click="deleteUser">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
