/**
 * Every word the interface shows, in Spanish, in one place.
 *
 * This module exists because of a contract decision recorded in DECISIONS.md:
 * the API answers in English, since it is a machine interface, and the screen
 * keeps its own wording and never renders the API's `message`. That only holds
 * while this file is the single source of visible text. A string written
 * inline in a component silently opts out of it, so components read from here
 * and hold no literals of their own.
 */
export const copy = {
  app: {
    /** Product name, shown in the page header. */
    name: 'Gestión de promociones',
  },

  summary: {
    title: 'Resumen',
    byStatusTitle: 'Por estado',
    validityTitle: 'Vigencia',
    /** Announced to assistive technology while the counters are being read. */
    loading: 'Cargando el resumen de promociones.',
    /**
     * Says out loud that the three status counters partition the collection,
     * so the number beside them is not read as a fourth slice of the same pie.
     */
    byStatusHint: 'Cada promoción está en uno de estos tres estados, y entre los tres suman el total.',
    validityHint:
      'Una promoción vigente hoy puede estar en cualquiera de los tres estados, así que este número se solapa con ellos y no se suma al total.',
  },

  counters: {
    scheduled: 'Programadas',
    active: 'Activas',
    finished: 'Finalizadas',
    validToday: 'Vigentes hoy',
  },

  actions: {
    retry: 'Reintentar',
  },

  /**
   * One text per failure the interface can face, chosen by the API's `code`
   * and never by its message.
   */
  errors: {
    VALIDATION_FAILED: 'Revisa los datos: alguno no es válido.',
    PROMOTION_NOT_FOUND: 'La promoción ya no existe. Puede que se haya eliminado.',
    PROMOTION_FINISHED: 'La promoción ya está finalizada y no admite cambios.',
    INVALID_STATUS_TRANSITION: 'Ese cambio de estado no está permitido.',
    PROMOTION_NOT_SCHEDULED: 'Solo se pueden eliminar las promociones programadas.',
    PROMOTION_STATE_CONFLICT: 'El estado actual de la promoción no permite esta operación.',
    /** `fetch` failed before there was a response to read. */
    network: 'No se pudo conectar con el servidor. Comprueba tu conexión e inténtalo de nuevo.',
    /** Anything else: an unparseable body, a proxy error, a bug. */
    unexpected: 'Se produjo un error inesperado. Inténtalo de nuevo.',
  },
} as const
