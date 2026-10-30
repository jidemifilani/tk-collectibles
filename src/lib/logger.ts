/**
 * Minimal structured logger. Swapping in a real provider (Sentry, Logtail,
 * etc.) later only means changing this one file — call sites stay the same.
 */
type Context = Record<string, unknown>;

function line(level: string, message: string, context?: Context): string {
  const entry = {
    level,
    message,
    time: new Date().toISOString(),
    ...(context ? { context } : {}),
  };
  return JSON.stringify(entry);
}

export const logger = {
  info(message: string, context?: Context) {
    console.log(line("info", message, context));
  },
  warn(message: string, context?: Context) {
    console.warn(line("warn", message, context));
  },
  error(message: string, error?: unknown, context?: Context) {
    const errInfo =
      error instanceof Error
        ? { name: error.name, message: error.message, stack: error.stack }
        : error;
    console.error(line("error", message, { ...context, error: errInfo }));
  },
};
