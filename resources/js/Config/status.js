/**
 * Diccionario centralizado de estados para toda la plataforma de supervisión.
 */
export const statusConfig = {
  // Estados de Jornadas Laborales
  workday: {
    OPEN: {
      label: 'En Curso',
      variant: 'success',
      badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      dotClass: 'bg-emerald-500 animate-pulse'
    },
    CLOSED: {
      label: 'Finalizada',
      variant: 'neutral',
      badgeClass: 'bg-slate-100 text-slate-600 border-slate-200',
      dotClass: 'bg-slate-400'
    },
    AUTO_CLOSED: {
      label: 'Cierre Auto',
      variant: 'warning',
      badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
      dotClass: 'bg-amber-500'
    },
    FORCED_CLOSED: {
      label: 'Cierre Supervisor',
      variant: 'danger',
      badgeClass: 'bg-rose-50 text-rose-700 border-rose-200',
      dotClass: 'bg-rose-500'
    },
    UNKNOWN: {
      label: 'Sin Registro',
      variant: 'neutral',
      badgeClass: 'bg-slate-50 text-slate-500 border-slate-200',
      dotClass: 'bg-slate-300'
    }
  },

  // Estados de Visitas en Terreno
  visita: {
    REALIZADA: {
      label: 'Realizada',
      variant: 'success',
      badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200'
    },
    PENDIENTE: {
      label: 'Pendiente',
      variant: 'neutral',
      badgeClass: 'bg-slate-100 text-slate-600 border-slate-200'
    },
    FUERA_RUTA: {
      label: 'Fuera de Ruta',
      variant: 'warning',
      badgeClass: 'bg-amber-50 text-amber-700 border-amber-200'
    },
    CANCELADA: {
      label: 'No Realizada',
      variant: 'danger',
      badgeClass: 'bg-rose-50 text-rose-700 border-rose-200'
    }
  },

  // Estados de Telemetría GPS
  telemetry: {
    ONLINE: {
      label: 'En Línea',
      variant: 'success',
      dotClass: 'bg-emerald-500 animate-ping'
    },
    MOVING: {
      label: 'En Tránsito',
      variant: 'info',
      dotClass: 'bg-sky-500 animate-pulse'
    },
    STOPPED: {
      label: 'Detenido',
      variant: 'warning',
      dotClass: 'bg-amber-500'
    },
    OFFLINE: {
      label: 'Sin Señal',
      variant: 'danger',
      dotClass: 'bg-rose-500'
    }
  },

  // Helpers de resolución rápida
  getWorkdayStatus(statusKey) {
    const key = String(statusKey || '').toUpperCase();
    return this.workday[key] || this.workday.UNKNOWN;
  },

  getVisitaStatus(statusKey) {
    const key = String(statusKey || '').toUpperCase();
    return this.visita[key] || this.visita.PENDIENTE;
  }
};

