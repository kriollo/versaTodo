<script setup lang="ts">
    import { inject, onMounted, reactive, ref } from 'vue';

    import modal from '@/dashboard/js/components/modal.vue';
    import { showErrorResponse, VersaToast } from '@/dashboard/js/functions';
    import { api, confirmar, type Etiqueta, type Miembro, type Nota, PRIORIDADES, type Tarea } from '@/dashboard/js/tableros/api';

    const props = defineProps<{ tarea: Tarea; etiquetas: Etiqueta[]; miembros: Miembro[] }>();
    const emit = defineEmits<{ cerrar: []; cambio: [] }>();

    const usuario = inject<Record<string, unknown>>('current_user', {});

    const form = reactive({
        titulo: props.tarea.titulo,
        descripcion: props.tarea.descripcion ?? '',
        prioridad: props.tarea.prioridad,
        fecha_vence: props.tarea.fecha_vence ?? '',
        id_responsable: props.tarea.id_responsable ?? '',
        etiquetas: [...props.tarea.etiquetas],
    });
    const notas = ref<Nota[]>([]);
    const nuevaNota = ref('');
    const guardando = ref(false);

    const cargarNotas = async () => {
        const res = await api('GET', `/tareas/${props.tarea.id}/notas`);
        if (res.success) notas.value = res.data;
    };
    onMounted(cargarNotas);

    const guardar = async () => {
        guardando.value = true;
        const res = await api('PUT', `/tareas/${props.tarea.id}`, { ...form });
        guardando.value = false;
        if (!res.success) return showErrorResponse(res);
        VersaToast.fire({ icon: 'success', title: 'Tarea guardada' });
        emit('cambio');
        emit('cerrar');
    };

    const eliminar = async () => {
        if (!(await confirmar(`¿Eliminar la tarea "${props.tarea.titulo}"?`))) return;
        const res = await api('DELETE', `/tareas/${props.tarea.id}`);
        if (!res.success) return showErrorResponse(res);
        emit('cambio');
        emit('cerrar');
    };

    const agregarNota = async () => {
        if (nuevaNota.value.trim() === '') return;
        const res = await api('POST', `/tareas/${props.tarea.id}/notas`, { contenido: nuevaNota.value });
        if (!res.success) return showErrorResponse(res);
        nuevaNota.value = '';
        await cargarNotas();
        emit('cambio');
    };

    const eliminarNota = async (nota: Nota) => {
        const res = await api('DELETE', `/notas/${nota.id}`);
        if (!res.success) return showErrorResponse(res);
        await cargarNotas();
        emit('cambio');
    };

    const alternarEtiqueta = (id: number) => {
        const i = form.etiquetas.indexOf(id);
        if (i === -1) form.etiquetas.push(id);
        else form.etiquetas.splice(i, 1);
    };

    const accion = (e: { accion: string }) => {
        if (e.accion === 'closeModal') emit('cerrar');
    };

    const campo =
        'w-full rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white';
</script>
<template>
    <modal idModal="modalTarea" :showModal="true" size="max-w-2xl" @accion="accion">
        <template #modalTitle>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Tarea #{{ tarea.id }}</h3>
        </template>
        <template #modalBody>
            <form id="formTarea" class="grid gap-4 sm:grid-cols-2" @submit.prevent="guardar">
                <label class="sm:col-span-2 text-sm font-medium text-gray-900 dark:text-white">
                    Título
                    <input v-model="form.titulo" :class="campo" maxlength="200" required />
                </label>
                <label class="sm:col-span-2 text-sm font-medium text-gray-900 dark:text-white">
                    Descripción
                    <textarea v-model="form.descripcion" :class="campo" rows="3" maxlength="5000"></textarea>
                </label>
                <label class="text-sm font-medium text-gray-900 dark:text-white">
                    Prioridad
                    <select v-model="form.prioridad" :class="campo">
                        <option v-for="(p, clave) in PRIORIDADES" :key="clave" :value="clave">{{ p.titulo }}</option>
                    </select>
                </label>
                <label class="text-sm font-medium text-gray-900 dark:text-white">
                    Vence
                    <input v-model="form.fecha_vence" type="date" :class="campo" />
                </label>
                <label class="sm:col-span-2 text-sm font-medium text-gray-900 dark:text-white">
                    Responsable
                    <select v-model="form.id_responsable" :class="campo">
                        <option value="">Sin asignar</option>
                        <option v-for="m in miembros" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                </label>
                <div class="sm:col-span-2">
                    <p class="mb-1 text-sm font-medium text-gray-900 dark:text-white">Etiquetas</p>
                    <p v-if="etiquetas.length === 0" class="text-xs text-gray-500">
                        Este tablero aún no tiene etiquetas.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="e in etiquetas"
                            :key="e.id"
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-medium text-white transition"
                            :class="form.etiquetas.includes(e.id) ? 'ring-2 ring-offset-1 ring-gray-900 dark:ring-white' : 'opacity-40'"
                            :style="{ backgroundColor: e.color }"
                            :aria-pressed="form.etiquetas.includes(e.id)"
                            @click="alternarEtiqueta(e.id)">
                            {{ e.nombre }}
                        </button>
                    </div>
                </div>
            </form>

            <section class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-600">
                <h4 class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Notas ({{ notas.length }})</h4>
                <ul class="mb-3 max-h-56 space-y-2 overflow-y-auto">
                    <li v-for="n in notas" :key="n.id" class="rounded-lg bg-gray-50 p-2 text-sm dark:bg-gray-700">
                        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ n.autor }} · {{ n.created_at.slice(0, 16) }}</span>
                            <button
                                v-if="n.id_usuario === usuario.id"
                                type="button"
                                class="text-red-600 hover:underline"
                                @click="eliminarNota(n)">
                                Eliminar
                            </button>
                        </div>
                        <p class="whitespace-pre-line text-gray-900 dark:text-white">{{ n.contenido }}</p>
                    </li>
                </ul>
                <form class="flex gap-2" @submit.prevent="agregarNota">
                    <input v-model="nuevaNota" :class="campo" placeholder="Escribe una nota…" maxlength="5000" />
                    <button type="submit" class="rounded-lg bg-gray-800 px-3 text-sm text-white hover:bg-gray-900 dark:bg-gray-600">
                        Agregar
                    </button>
                </form>
            </section>
        </template>
        <template #modalFooter>
            <div class="flex w-full justify-between">
                <button type="button" class="rounded-lg px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-gray-700" @click="eliminar">
                    Eliminar tarea
                </button>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-900 hover:bg-gray-100 dark:border-gray-600 dark:text-white dark:hover:bg-gray-700"
                        @click="emit('cerrar')">
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        form="formTarea"
                        :disabled="guardando"
                        class="rounded-lg bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800 disabled:opacity-50">
                        Guardar
                    </button>
                </div>
            </div>
        </template>
    </modal>
</template>
