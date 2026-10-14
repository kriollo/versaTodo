<script setup lang="ts">
    import { inject, onMounted, reactive, ref, watch } from 'vue';

    import breadcrumb from '@/dashboard/js/components/breadcrumb.vue';
    import { showErrorResponse, VersaToast } from '@/dashboard/js/functions';
    import { api, confirmar } from '@/dashboard/js/tableros/api';
    import tableroKanban from '@/dashboard/js/tableros/tableroKanban.vue';

    interface Tablero {
        id: number;
        nombre: string;
        descripcion: string | null;
        color: string;
        total: number;
        hechas: number;
        vencidas: number;
    }

    const panelUrl = inject<string>('panelUrl', 'admin');
    const flecha =
        '<svg class="w-3 h-3 text-gray-400 mx-1" fill="none" viewBox="0 0 6 10" xmlns="http://www.w3.org/2000/svg"><path d="m1 9 4-4-4-4" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>';
    const breadCrumb = [
        { type: 'link', title: 'Home', icon: '', link: `/${panelUrl}/dashboard` },
        { type: 'text', title: 'Tableros', icon: flecha, link: '' },
    ];

    const tableros = ref<Tablero[]>([]);
    const cargando = ref(true);
    const nuevo = reactive({ nombre: '', descripcion: '', color: '#2563eb' });

    // El tablero abierto vive en la URL (?t=ID): recargar o compartir el enlace abre el mismo
    const abierto = ref<number | null>(Number(new URLSearchParams(location.search).get('t')) || null);
    watch(abierto, id => {
        const url = new URL(location.href);
        if (id) url.searchParams.set('t', String(id));
        else url.searchParams.delete('t');
        history.replaceState(history.state, '', url);
        if (!id) cargar();
    });

    const cargar = async () => {
        const res = await api('GET', '/tableros');
        cargando.value = false;
        if (res.success) tableros.value = res.data;
    };
    onMounted(() => {
        if (!abierto.value) cargar();
    });

    const crear = async () => {
        const res = await api('POST', '/tableros', { ...nuevo });
        if (!res.success) return showErrorResponse(res);
        VersaToast.fire({ icon: 'success', title: 'Tablero creado' });
        nuevo.nombre = '';
        nuevo.descripcion = '';
        abierto.value = res.id ?? null;
    };

    const eliminar = async (t: Tablero) => {
        if (!(await confirmar(`¿Eliminar "${t.nombre}" y sus ${t.total} tareas?`))) return;
        const res = await api('DELETE', `/tableros/${t.id}`);
        if (!res.success) return showErrorResponse(res);
        await cargar();
    };

    const campo =
        'rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white';
</script>
<template>
    <div class="flex h-full w-full flex-col">
        <breadcrumb title="Tableros" :items="breadCrumb" />
        <div class="mx-4 flex-1 overflow-y-auto pb-6">
            <tableroKanban v-if="abierto" :id="abierto" :key="abierto" @volver="abierto = null" />

            <template v-else>
                <form
                    class="mb-6 flex flex-wrap items-end gap-2 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                    @submit.prevent="crear">
                    <label class="text-sm font-medium text-gray-900 dark:text-white">
                        Nuevo tablero
                        <input v-model="nuevo.nombre" :class="campo" class="block w-64" maxlength="120" required placeholder="Nombre" />
                    </label>
                    <input v-model="nuevo.descripcion" :class="campo" class="w-80" maxlength="500" placeholder="Descripción (opcional)" aria-label="Descripción" />
                    <input v-model="nuevo.color" type="color" class="h-10 w-12 rounded" aria-label="Color" />
                    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800">Crear</button>
                </form>

                <p v-if="cargando" class="text-gray-500">Cargando…</p>
                <p v-else-if="tableros.length === 0" class="text-gray-500 dark:text-gray-400">
                    Todavía no hay tableros. Crea el primero arriba.
                </p>

                <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <li
                        v-for="t in tableros"
                        :key="t.id"
                        class="flex flex-col rounded-xl border-t-4 bg-white p-4 shadow-sm dark:bg-gray-800"
                        :style="{ borderTopColor: t.color }">
                        <button type="button" class="text-start" @click="abierto = t.id">
                            <h3 class="text-lg font-semibold text-gray-900 hover:underline dark:text-white">{{ t.nombre }}</h3>
                            <p v-if="t.descripcion" class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">{{ t.descripcion }}</p>
                        </button>
                        <div class="mt-4">
                            <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                <div
                                    class="h-full rounded-full"
                                    :style="{ width: (t.total ? (t.hechas / t.total) * 100 : 0) + '%', backgroundColor: t.color }"></div>
                            </div>
                            <div class="mt-2 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ t.hechas }}/{{ t.total }} hechas</span>
                                <span v-if="t.vencidas > 0" class="font-semibold text-red-600">⚠ {{ t.vencidas }} vencidas</span>
                                <button type="button" class="ms-auto text-red-600 hover:underline" @click="eliminar(t)">Eliminar</button>
                            </div>
                        </div>
                    </li>
                </ul>
            </template>
        </div>
    </div>
</template>
