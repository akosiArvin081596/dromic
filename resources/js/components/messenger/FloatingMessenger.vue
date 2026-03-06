<script setup lang="ts">
import { MessageCircle } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useMessenger } from '@/composables/useMessenger';
import MessengerPanel from './MessengerPanel.vue';

const isOpen = ref(false);

const { unreadCount, fetchUnreadCount } = useMessenger();

const totalUnread = computed(() => unreadCount.value);

onMounted(fetchUnreadCount);

// Draggable FAB logic
const fabRef = ref<HTMLButtonElement | null>(null);
const isDragging = ref(false);
const wasDragged = ref(false);
const isPointerDown = ref(false);
const pos = ref({ x: 0, y: 0 });
const dragStart = { x: 0, y: 0, fabX: 0, fabY: 0 };
let rafId = 0;
let pendingPos = { x: 0, y: 0 };

const FAB_SIZE = 56;
const MARGIN = 24;
const DRAG_THRESHOLD = 12;

function clamp(value: number, min: number, max: number): number {
    return Math.max(min, Math.min(max, value));
}

function initPosition() {
    pos.value = {
        x: window.innerWidth - FAB_SIZE - MARGIN,
        y: window.innerHeight - FAB_SIZE - MARGIN,
    };
}

function applyPosition() {
    pos.value = { ...pendingPos };
    rafId = 0;
}

function onPointerDown(e: PointerEvent) {
    isPointerDown.value = true;
    wasDragged.value = false;
    dragStart.x = e.clientX;
    dragStart.y = e.clientY;
    dragStart.fabX = pos.value.x;
    dragStart.fabY = pos.value.y;
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
    e.preventDefault();
}

function onPointerMove(e: PointerEvent) {
    if (!isPointerDown.value) return;

    const dx = e.clientX - dragStart.x;
    const dy = e.clientY - dragStart.y;
    const dist = Math.abs(dx) + Math.abs(dy);

    // Only start dragging after exceeding threshold
    if (!wasDragged.value) {
        if (dist <= DRAG_THRESHOLD) return;
        wasDragged.value = true;
        isDragging.value = true;
    }

    pendingPos = {
        x: clamp(dragStart.fabX + dx, MARGIN, window.innerWidth - FAB_SIZE - MARGIN),
        y: clamp(dragStart.fabY + dy, MARGIN, window.innerHeight - FAB_SIZE - MARGIN),
    };

    if (!rafId) {
        rafId = requestAnimationFrame(applyPosition);
    }
}

function onPointerUp() {
    isPointerDown.value = false;
    isDragging.value = false;
    if (rafId) {
        cancelAnimationFrame(rafId);
        rafId = 0;
        applyPosition();
    }
}

function onFabClick() {
    if (!wasDragged.value) {
        isOpen.value = !isOpen.value;
    }
}

function onResize() {
    pos.value = {
        x: clamp(pos.value.x, MARGIN, window.innerWidth - FAB_SIZE - MARGIN),
        y: clamp(pos.value.y, MARGIN, window.innerHeight - FAB_SIZE - MARGIN),
    };
}

// Panel position: above the FAB, centered horizontally
const panelStyle = computed(() => {
    const panelW = 384;
    const panelH = 512;
    const gap = 12;

    let left = pos.value.x + FAB_SIZE / 2 - panelW / 2;
    left = clamp(left, MARGIN, window.innerWidth - panelW - MARGIN);

    let top = pos.value.y - panelH - gap;
    if (top < MARGIN) {
        top = pos.value.y + FAB_SIZE + gap;
    }

    return { left: `${left}px`, top: `${top}px` };
});

onMounted(() => {
    initPosition();
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    if (rafId) cancelAnimationFrame(rafId);
});
</script>

<template>
    <div>
        <!-- Messenger Panel -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-4 opacity-0"
        >
            <MessengerPanel v-if="isOpen" :style="panelStyle" class="!fixed z-50" @close="isOpen = false" />
        </Transition>

        <!-- FAB Button -->
        <button
            ref="fabRef"
            class="fixed z-50 flex h-14 w-14 touch-none items-center justify-center rounded-full bg-indigo-600 text-white shadow-lg will-change-transform select-none hover:bg-indigo-700"
            :class="isDragging ? 'scale-110 cursor-grabbing shadow-2xl' : 'cursor-pointer transition-shadow duration-200'"
            :style="{ transform: `translate3d(${pos.x}px, ${pos.y}px, 0)`, left: '0px', top: '0px' }"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @click="onFabClick"
        >
            <MessageCircle :size="24" />
            <span
                v-if="totalUnread > 0"
                class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[11px] font-bold text-white"
            >
                {{ totalUnread > 9 ? '9+' : totalUnread }}
            </span>
        </button>
    </div>
</template>
