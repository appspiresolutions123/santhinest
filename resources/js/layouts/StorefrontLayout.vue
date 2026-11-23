<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Mail, MapPin, Menu, Phone, X } from '@lucide/vue';
import { useWindowScroll } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import logoUrl from '@/assets/brand/santhi-nest-logo.svg';
import BrandMark from '@/components/storefront/BrandMark.vue';
import { Toaster } from '@/components/ui/sonner';
import { dashboard, home, login } from '@/routes';
import type { PropertyCard, PropertyDetails } from '@/types';

const page = usePage<{
    properties?: PropertyCard[];
    property?: PropertyDetails;
}>();
const { y } = useWindowScroll();

const isScrolled = computed(() => y.value > 40);
const isMenuOpen = ref(false);
const canOpenDashboard = computed(
    () => page.props.auth?.user && page.props.auth.can?.viewDashboard,
);
const contactProperties = computed(() => {
    const { properties, property } = page.props;
    const homes = property ? [property] : (properties ?? []);

    return homes.map((home) => ({
        ...home,
        fullAddress: [home.address, home.location].filter(Boolean).join(', '),
    }));
});
const year = new Date().getFullYear();

watch(
    () => page.url,
    () => (isMenuOpen.value = false),
);

const links = [
    { label: 'Why shanthinest.com', href: '/#highlights' },
    { label: 'Our homes', href: '/#homes' },
    { label: 'How it works', href: '/#how-it-works' },
    { label: 'Contact', href: '#contact' },
];
</script>

<template>
    <div class="min-h-screen bg-ivory font-sans text-ink antialiased">
        <header
            class="fixed inset-x-0 top-0 z-50 transition-all duration-500"
            :class="
                isScrolled || isMenuOpen
                    ? 'border-b border-ink/5 bg-ivory/85 text-ink shadow-[0_8px_30px_-12px_rgba(29,26,22,0.18)] backdrop-blur-xl'
                    : 'bg-gradient-to-b from-black/45 to-transparent text-white'
            "
        >
            <div
                class="mx-auto flex h-18 max-w-7xl items-center justify-between px-5 sm:px-8"
            >
                <Link
                    :href="home()"
                    class="group flex items-center gap-2.5"
                    aria-label="shanthinest.com home"
                >
                    <BrandMark
                        class="size-12 transition-transform duration-500 group-hover:-rotate-6"
                    />
                    <span
                        class="font-display text-[1.4rem] leading-none tracking-tight"
                    >
                        shanthinest.com
                    </span>
                </Link>

                <nav class="hidden items-center gap-9 text-sm md:flex">
                    <a
                        v-for="link in links"
                        :key="link.label"
                        :href="link.href"
                        class="relative py-1 opacity-85 transition after:absolute after:inset-x-0 after:-bottom-0.5 after:h-px after:origin-left after:scale-x-0 after:bg-current after:transition-transform after:duration-300 hover:opacity-100 hover:after:scale-x-100"
                    >
                        {{ link.label }}
                    </a>
                    <Link
                        v-if="canOpenDashboard"
                        :href="dashboard()"
                        class="inline-flex items-center gap-1.5 rounded-full border border-current/25 px-4 py-2 text-xs font-medium tracking-wide uppercase transition hover:bg-current/10"
                    >
                        Admin
                        <ArrowUpRight class="size-3.5" />
                    </Link>
                </nav>

                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-full md:hidden"
                    :aria-expanded="isMenuOpen"
                    aria-label="Toggle menu"
                    @click="isMenuOpen = !isMenuOpen"
                >
                    <X v-if="isMenuOpen" class="size-5" />
                    <Menu v-else class="size-5" />
                </button>
            </div>

            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="-translate-y-2 opacity-0"
                leave-active-class="transition duration-200 ease-in"
                leave-to-class="-translate-y-2 opacity-0"
            >
                <nav
                    v-if="isMenuOpen"
                    class="border-t border-ink/5 px-5 pt-2 pb-6 md:hidden"
                >
                    <a
                        v-for="link in links"
                        :key="link.label"
                        :href="link.href"
                        class="block border-b border-ink/5 py-4 font-display text-2xl"
                        @click="isMenuOpen = false"
                    >
                        {{ link.label }}
                    </a>
                    <Link
                        v-if="canOpenDashboard"
                        :href="dashboard()"
                        class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-clay"
                    >
                        Open admin <ArrowUpRight class="size-4" />
                    </Link>
                </nav>
            </Transition>
        </header>

        <main>
            <slot />
        </main>

        <footer class="relative overflow-hidden bg-ink text-ivory">
            <div
                class="sn-grain pointer-events-none absolute inset-0 opacity-[0.07]"
            />
            <div
                class="relative mx-auto grid max-w-7xl gap-12 px-5 py-20 sm:px-8 md:grid-cols-[1.4fr_1fr]"
            >
                <div>
                    <div
                        class="inline-block rounded-[1.75rem] bg-ivory p-4 shadow-2xl shadow-black/30"
                    >
                        <img
                            :src="logoUrl"
                            alt="shanthinest.com Service Apartments — Stay peacefully"
                            class="h-40 w-auto"
                            loading="lazy"
                        />
                    </div>
                    <p
                        class="mt-6 max-w-sm text-sm leading-relaxed text-ivory/65"
                    >
                        Calm, beautifully kept homes for slow mornings and easy
                        evenings. Send a request, and we will personally confirm
                        your stay.
                    </p>
                </div>
                <div>
                    <h3
                        class="text-xs font-medium tracking-[0.2em] text-honey uppercase"
                    >
                        Explore
                    </h3>
                    <ul class="mt-5 space-y-3 text-sm text-ivory/75">
                        <li v-for="link in links" :key="link.label">
                            <a
                                :href="link.href"
                                class="transition hover:text-ivory"
                            >
                                {{ link.label }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <section
                id="contact"
                aria-labelledby="contact-heading"
                class="relative mx-auto max-w-7xl scroll-mt-18 border-t border-ivory/10 px-5 py-12 sm:px-8"
            >
                <h2
                    id="contact-heading"
                    class="font-display text-4xl font-light md:text-5xl"
                >
                    Contact & location
                </h2>
                <div
                    v-if="contactProperties.length"
                    class="mt-8 grid gap-5 lg:grid-cols-2"
                >
                    <article
                        v-for="property in contactProperties"
                        :key="property.id"
                        class="min-w-0 rounded-3xl border border-ivory/15 bg-ivory/5 p-6 sm:p-8"
                    >
                        <h3 class="font-display text-2xl">{{ property.name }}</h3>
                        <div class="mt-5 grid gap-4 text-sm">
                            <a
                                v-if="property.fullAddress"
                                :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(property.fullAddress)}`"
                                class="flex items-start gap-3 underline underline-offset-4 hover:text-honey"
                            >
                                <MapPin
                                    class="mt-0.5 size-4 shrink-0 text-honey"
                                    aria-hidden="true"
                                />
                                <span>
                                    {{ property.fullAddress }}
                                    <span class="block text-xs">
                                        Open in Google Maps
                                    </span>
                                </span>
                            </a>
                            <a
                                v-if="property.contactPhone"
                                :href="`tel:${property.contactPhone.replace(/[\s().-]/g, '')}`"
                                class="flex items-start gap-3 underline underline-offset-4 hover:text-honey"
                            >
                                <Phone
                                    class="mt-0.5 size-4 shrink-0 text-honey"
                                    aria-hidden="true"
                                />
                                {{ property.contactPhone }}
                            </a>
                            <a
                                v-if="property.contactEmail"
                                :href="`mailto:${encodeURIComponent(property.contactEmail).replace(/%40/g, '@')}`"
                                class="flex items-start gap-3 underline underline-offset-4 hover:text-honey"
                            >
                                <Mail
                                    class="mt-0.5 size-4 shrink-0 text-honey"
                                    aria-hidden="true"
                                />
                                <span class="wrap-anywhere">
                                    {{ property.contactEmail }}
                                </span>
                            </a>
                        </div>
                        <iframe
                            v-if="property.fullAddress"
                            :src="`https://www.google.com/maps?q=${encodeURIComponent(property.fullAddress)}&output=embed`"
                            :title="`Map for ${property.name}`"
                            class="mt-5 block h-64 w-full rounded-2xl border border-ivory/10"
                            loading="lazy"
                            allowfullscreen
                        />
                    </article>
                </div>
                <p v-else class="mt-5 text-sm text-ivory/75">
                    Contact details will appear when a property is published.
                </p>
            </section>
            <div
                class="relative mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 border-t border-ivory/10 px-5 py-6 text-xs text-ivory/45 sm:px-8"
                :class="{ 'pb-24 lg:pb-6': page.props.property }"
            >
                <span>© {{ year }} shanthinest.com. All rights reserved.</span>
                <Link :href="login()" class="transition hover:text-ivory/80">
                    Host login
                </Link>
            </div>
        </footer>

        <Toaster position="top-center" rich-colors />
    </div>
</template>
