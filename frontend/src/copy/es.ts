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

  promotions: {
    title: 'Promociones',
    /**
     * The table's caption. It is read before the table and never shown, since
     * the visible heading above already names the section and repeating it in
     * larger type would only push the first row further down. What it adds is
     * what a sighted reader gets from the header row at a glance: what the
     * columns are and how the rows are ordered.
     */
    caption:
      'Listado de promociones, de la fecha de inicio más reciente a la más antigua. Cada fila indica el nombre, a qué se aplica, el descuento, las fechas, el estado, si está vigente hoy y las acciones disponibles.',
    loading: 'Cargando el listado de promociones.',
    emptyTitle: 'Todavía no hay promociones.',
    emptyHint: 'Cuando se cree la primera, aparecerá en este listado.',

    columns: {
      name: 'Nombre',
      target: 'Se aplica a',
      discount: 'Descuento',
      dates: 'Fechas',
      status: 'Estado',
      validity: 'Vigencia hoy',
      actions: 'Acciones',
    },

    /**
     * The two halves of the join between the dates of a range. On screen it is
     * the dash; a dash is not reliably announced, so assistive technology is
     * given the word instead and the dash is hidden from it. Both live here so
     * the row holds no visible character of its own.
     */
    dateRangeMark: '–',
    dateRangeSeparator: 'hasta',

    /**
     * Stands where the buttons are on every other row. A finished promotion
     * admits no action at all, and leaving the cell empty would read as a
     * layout accident rather than as the rule it is.
     */
    noActions: 'Finalizada: no admite cambios.',
  },

  /** Whether a promotion applies to a single product or to a whole category. */
  targetTypes: {
    product: 'Producto',
    category: 'Categoría',
  },

  discountTypes: {
    PERCENTAGE: 'Porcentaje',
    FIXED_AMOUNT: 'Importe fijo',
  },

  /** The three lifecycle states, as the user reads them. */
  statuses: {
    SCHEDULED: 'Programada',
    ACTIVE: 'Activa',
    FINISHED: 'Finalizada',
  },

  /**
   * Whether today falls inside the promotion's dates. Worded in full on both
   * sides rather than as "Sí" and "No": the cell is read out of context by a
   * screen reader, and a bare "No" next to a status called "Activa" is exactly
   * the confusion this column has to avoid.
   */
  validity: {
    valid: 'Vigente hoy',
    notValid: 'No vigente hoy',
  },

  /**
   * How Spanish writes a number. Kept beside the words because it is part of
   * the same choice of language, and read by the formatters so no component
   * decides on its own where a comma goes.
   */
  numbers: {
    decimalSeparator: ',',
    groupSeparator: '.',
    /**
     * Written as an escape and not typed literally: it is a non breaking
     * space, so the sign can never wrap away from its number, and an
     * invisible character in the source is one nobody can maintain.
     */
    percentSuffix: '\u00a0%',
  },

  actions: {
    retry: 'Reintentar',
    activate: 'Activar',
    finish: 'Finalizar',
    edit: 'Editar',
    delete: 'Eliminar',
    /**
     * Names the promotion a button acts on, for whoever hears the buttons one
     * by one instead of seeing the row they sit in. Six buttons that all
     * announce "Editar" say nothing about which of six promotions they edit.
     */
    forPromotion: (action: string, promotionName: string) =>
      `${action} la promoción ${promotionName}`,
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
