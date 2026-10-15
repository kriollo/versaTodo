<script setup lang="ts">
    import { computed, onMounted, reactive, ref } from 'vue';

    import { showErrorResponse } from '@/dashboard/js/functions';
    import {
        api,
        type Columna,
        COLUMNAS,
        confirmar,
        type Etiqueta,
        hoy,
        type Miembro,
        PRIORIDADES,
        type Tarea,
    } from '@/dashboard/js/tableros/api';
    import tareaModal from '@/dashboard/js/tableros/tareaModal.vue';

    const props = defineProps<{ id: number }>();
    const emit = defineEmits<{ volver: [] }>();

    const tablero = ref<{ id: number; nombre: string; descripcion: string | null; color: string } | null>(null);
    const tareas = ref<Tarea[]>([]);
    const etiquetas = ref<Etiqueta[]>([]);
    const miembros = ref<Miembro[]>([]);
    const cargando = ref(true);

    const busqueda = ref('');
    const filtroEtiqueta = ref<number | ''>('');
    const nuevas = reactive<Record<Columna, string>>({ todo: '', doing: '', done: '' });
    const abierta = ref<Tarea | null>(null);
    const verEtiquetas = ref(false);
    const nuevaEtiqueta = reactive({ nombre: '', color: '#64748b' });

    const cargar = async () => {
        const res = await api('GET', `/tableros/${props.id}`);
        cargando.value = false;
        if (!res.success) {
            showErrorResponse(res);
            emit('volver');
            return;
        }
        tablero.value = res.tablero;
        tareas.value = res.tareas;
        etiquetas.value = res.etiquetas;
        miembros.value = res.miembros;
    };
    onMounted(cargar);

    const visibles = computed(() => {
        const texto = busqueda.value.trim().toLowerCase();
        return tareas.value.filter(
            t =>
                (texto === '' || t.titulo.toLowerCase().includes(texto)) &&
                (filtroEtiqueta.value === '' || t.etiquetas.includes(filtroEtiqueta.value)),
        );
    });
    const deColumna = (columna: Columna) =>
        visibles.value.filter(t => t.columna === columna).sort((a, b) => a.posicion - b.posicion);
    const etiqueta = (id: number) => etiquetas.value.find(e => e.id === id);
    const vencida = (t: Tarea) => t.fecha_vence !== null && t.columna !== 'done' && t.fecha_vence < hoy();
    const iniciales = (nombre: string) =>
        nombre
            .split(' ')
            .map(p => p[0])
            .join('')
            .slice(0, 2)
            .toUpperCase();

    // ---------- Mover tareas ----------

    const arrastrando = ref<number | null>(null);
    const sobre = ref<Columna | null>(null);

    /** Deja la tarea en la columna, antes de otra tarea o al final, y guarda el orden de esa columna. */
    const mover = async (id: number, columna: Columna, antesDe: number | null) => {
        const tarea = tareas.value.find(t => t.id === id);
        if (!tarea || id === antesDe) {return;}

        // El orden se calcula con todas las tareas de la columna, no solo las filtradas
        const orden = tareas.value
            .filter(t => t.columna === columna && t.id !== id)
            .sort((a, b) => a.posicion - b.posicion)
            .map(t => t.id);
        const i = antesDe === null ? -1 : orden.indexOf(antesDe);
        orden.splice(i === -1 ? orden.length : i, 0, id);

        tarea.columna = columna;
        orden.forEach((tid, posicion) => {
            const t = tareas.value.find(x => x.id === tid);
            if (t) {t.posicion = posicion;}
        });

        const res = await api('PATCH', `/tareas/${id}/mover`, { columna, orden });
        if (!res.success) {
            showErrorResponse(res);
            await cargar();
        }
    };

    const soltar = (columna: Columna, antesDe: number | null) => {
        const id = arrastrando.value;
        arrastrando.value = null;
        sobre.value = null;
        if (id !== null) {mover(id, columna, antesDe);}
    };

    const vecina = (t: Tarea, paso: number): Columna | null => {
        const i = COLUMNAS.findIndex(c => c.id === t.columna) + paso;
        return COLUMNAS[i]?.id ?? null;
    };

    // ---------- Crear ----------

    const crearTarea = async (columna: Columna) => {
        const titulo = nuevas[columna].trim();
        if (titulo === '') {return;}
        const res = await api('POST', `/tableros/${props.id}/tareas`, { titulo, columna });
        if (!res.success) {return showErrorResponse(res);}
        nuevas[columna] = '';
        await cargar();
    };

    const crearEtiqueta = async () => {
        const res = await api('POST', `/tableros/${props.id}/etiquetas`, { ...nuevaEtiqueta });
        if (!res.success) {return showErrorResponse(res);}
        nuevaEtiqueta.nombre = '';
        await cargar();
    };

    const eliminarEtiqueta = async (e: Etiqueta) => {
        if (!(await confirmar(`¿Eliminar la etiqueta "${e.nombre}"? Se quitará de todas las tareas.`))) {return;}
        const res = await api('DELETE', `/etiquetas/${e.id}`);
        if (!res.success) {return showErrorResponse(res);}
        if (filtroEtiqueta.value === e.id) {filtroEtiqueta.value = '';}
        await cargar();
    };

    const campo =
        'rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white';
</script>
<template>
    <div class="flex h-full flex-col gap-4">
        <header class="flex flex-wrap items-center gap-3">
            <button
                type="button"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 hover:bg-gray-100 dark:border-gray-600 dark:text-white dark:hover:bg-gray-700"
                @click="emit('volver')">
                ← Tableros
            </button>
            <h2 v-if="tablero" class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white">
                <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: tablero.color }"></span>
                {{ tablero.nombre }}
            </h2>
            <div class="ms-auto flex flex-wrap gap-2">
                <input v-model="busqueda" type="search" :class="campo" placeholder="Buscar tarea…" aria-label="Buscar tarea" />
                <select v-model="filtroEtiqueta" :class="campo" aria-label="Filtrar por etiqueta">
                    <option value="">Todas las etiquetas</option>
                    <option v-for="e in etiquetas" :key="e.id" :value="e.id">{{ e.nombre }}</option>
                </select>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 hover:bg-gray-100 dark:border-gray-600 dark:text-white dark:hover:bg-gray-700"
                    :aria-expanded="verEtiquetas"
                    @click="verEtiquetas = !verEtiquetas">
                    Etiquetas
                </button>
            </div>
        </header>

        <section v-if="verEtiquetas" class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-3 flex flex-wrap gap-2">
                <span
                    v-for="e in etiquetas"
                    :key="e.id"
                    class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium text-white"
                    :style="{ backgroundColor: e.color }">
                    {{ e.nombre }}
                    <button type="button" class="ms-1 font-bold" :aria-label="`Eliminar ${e.nombre}`" @click="eliminarEtiqueta(e)">×</button>
                </span>
                <span v-if="etiquetas.length === 0" class="text-sm text-gray-500">Sin etiquetas todavía.</span>
            </div>
            <form class="flex flex-wrap gap-2" @submit.prevent="crearEtiqueta">
                <input v-model="nuevaEtiqueta.nombre" :class="campo" placeholder="Nueva etiqueta" maxlength="40" required />
                <input v-model="nuevaEtiqueta.color" type="color" class="h-10 w-12 rounded-sm" aria-label="Color" />
                <button type="submit" class="rounded-lg bg-blue-700 px-4 text-sm text-white hover:bg-blue-800">Agregar</button>
            </form>
        </section>

        <p v-if="cargando" class="text-gray-500">Cargando…</p>

        <div v-else class="grid flex-1 gap-4 md:grid-cols-3">
            <section
                v-for="col in COLUMNAS"
                :key="col.id"
                class="flex min-h-[200px] flex-col rounded-xl bg-gray-100 p-3 transition dark:bg-gray-800"
                :class="{ 'ring-2 ring-blue-500': sobre === col.id }"
                @dragover.prevent="sobre = col.id"
                @dragleave.self="sobre = null"
                @drop.prevent="soltar(col.id, null)">
                <h3 class="mb-3 flex items-center justify-between text-sm font-semibold uppercase text-gray-600 dark:text-gray-300">
                    {{ col.titulo }}
                    <span class="rounded-full bg-gray-200 px-2 text-xs dark:bg-gray-700">{{ deColumna(col.id).length }}</span>
                </h3>

                <ul class="flex flex-1 flex-col gap-2">
                    <li
                        v-for="t in deColumna(col.id)"
                        :key="t.id"
                        draggable="true"
                        class="group cursor-grab rounded-lg border border-gray-200 bg-white p-3 shadow-xs hover:shadow-sm dark:border-gray-700 dark:bg-gray-900"
                        :class="{ 'opacity-40': arrastrando === t.id }"
                        @dragstart="arrastrando = t.id"
                        @dragend="arrastrando = null"
                        @drop.prevent.stop="soltar(col.id, t.id)">
                        <div v-if="t.etiquetas.length" class="mb-2 flex flex-wrap gap-1">
                            <template v-for="idE in t.etiquetas" :key="idE">
                                <span
                                    v-if="etiqueta(idE)"
                                    class="rounded-sm px-2 text-[11px] font-medium text-white"
                                    :style="{ backgroundColor: etiqueta(idE)!.color }">
                                    {{ etiqueta(idE)!.nombre }}
                                </span>
                            </template>
                        </div>
                        <button
                            type="button"
                            class="w-full text-start text-sm font-medium text-gray-900 hover:underline dark:text-white"
                            :class="{ 'line-through text-gray-500 dark:text-gray-400': t.columna === 'done' }"
                            @click="abierta = t">
                            {{ t.titulo }}
                        </button>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-sm px-1.5 py-0.5" :class="PRIORIDADES[t.prioridad].clase">
                                {{ PRIORIDADES[t.prioridad].titulo }}
                            </span>
                            <span
                                v-if="t.fecha_vence"
                                :class="vencida(t) ? 'font-semibold text-red-600' : 'text-gray-500 dark:text-gray-400'"
                                :title="vencida(t) ? 'Vencida' : 'Vence'">
                                {{ vencida(t) ? '⚠ ' : '' }}{{ t.fecha_vence }}
                            </span>
                            <span v-if="t.notas > 0" class="text-gray-500 dark:text-gray-400" :title="`${t.notas} notas`">
                                💬 {{ t.notas }}
                            </span>
                            <span
                                v-if="t.responsable"
                                class="ms-auto inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white"
                                :title="t.responsable">
                                {{ iniciales(t.responsable) }}
                            </span>
                        </div>
                        <div class="mt-2 hidden justify-between group-hover:flex group-focus-within:flex">
                            <button
                                v-if="vecina(t, -1)"
                                type="button"
                                class="text-xs text-gray-500 hover:text-gray-900 dark:hover:text-white"
                                :aria-label="`Mover a ${COLUMNAS.find(c => c.id === vecina(t, -1))?.titulo}`"
                                @click="mover(t.id, vecina(t, -1)!, null)">
                                ◀
                            </button>
                            <span></span>
                            <button
                                v-if="vecina(t, 1)"
                                type="button"
                                class="text-xs text-gray-500 hover:text-gray-900 dark:hover:text-white"
                                :aria-label="`Mover a ${COLUMNAS.find(c => c.id === vecina(t, 1))?.titulo}`"
                                @click="mover(t.id, vecina(t, 1)!, null)">
                                ▶
                            </button>
                        </div>
                    </li>
                </ul>

                <form class="mt-3" @submit.prevent="crearTarea(col.id)">
                    <input
                        v-model="nuevas[col.id]"
                        :class="campo"
                        class="w-full"
                        placeholder="+ Agregar tarea"
                        maxlength="200"
                        :aria-label="`Nueva tarea en ${col.titulo}`" />
                </form>
            </section>
        </div>

        <tareaModal
            v-if="abierta"
            :tarea="abierta"
            :etiquetas="etiquetas"
            :miembros="miembros"
            @cerrar="abierta = null"
            @cambio="cargar" />
    </div>
</template>
