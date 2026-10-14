import Swal from 'sweetalert2';

import { getPanelUrl, versaFetch } from '@/dashboard/js/functions';
import type { VersaFetchResponse } from '@/dashboard/types/versaTypes.d';

export type Columna = 'todo' | 'doing' | 'done';
export type Prioridad = 'baja' | 'media' | 'alta';

export interface Etiqueta {
    id: number;
    nombre: string;
    color: string;
}

export interface Tarea {
    id: number;
    titulo: string;
    descripcion: string | null;
    columna: Columna;
    prioridad: Prioridad;
    fecha_vence: string | null;
    posicion: number;
    id_responsable: number | null;
    responsable: string | null;
    notas: number;
    etiquetas: number[];
}

export interface Miembro {
    id: number;
    name: string;
    email: string;
}

export interface Nota {
    id: number;
    contenido: string;
    created_at: string;
    id_usuario: number;
    autor: string;
}

export const COLUMNAS: { id: Columna; titulo: string }[] = [
    { id: 'todo', titulo: 'Por hacer' },
    { id: 'doing', titulo: 'En curso' },
    { id: 'done', titulo: 'Hecho' },
];

export const PRIORIDADES: Record<Prioridad, { titulo: string; clase: string }> = {
    alta: { titulo: 'Alta', clase: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' },
    media: { titulo: 'Media', clase: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' },
    baja: { titulo: 'Baja', clase: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200' },
};

/** Fecha de hoy en AAAA-MM-DD (hora local), para comparar con fecha_vence. */
export const hoy = (): string => new Date().toLocaleDateString('sv-SE');

export const api = (
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
    ruta: string,
    data?: Record<string, unknown>,
): Promise<VersaFetchResponse & Record<string, any>> =>
    versaFetch({
        url: `/${getPanelUrl()}/tableros/api${ruta}`,
        method,
        ...(data ? { data, headers: { 'Content-Type': 'application/json' } } : {}),
    });

export const confirmar = async (titulo: string): Promise<boolean> =>
    (
        await Swal.fire({
            title: titulo,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
        })
    ).isConfirmed;
