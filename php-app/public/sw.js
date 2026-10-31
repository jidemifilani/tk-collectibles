// Service worker: shows new-arrival notifications and opens the item when tapped.
self.addEventListener("push", (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch {
    data = { body: event.data && event.data.text() };
  }

  event.waitUntil(
    self.registration.showNotification(data.title || "New arrival!", {
      body: data.body || "Fresh pieces just dropped.",
      icon: "/tk-icon.png",
      image: data.image,
      data: { url: data.url || "/shop" },
    })
  );
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const url = new URL(event.notification.data.url, self.location.origin).href;

  event.waitUntil(
    clients.matchAll({ type: "window", includeUncontrolled: true }).then((list) => {
      for (const c of list) {
        if (c.url === url && "focus" in c) return c.focus();
      }
      return clients.openWindow(url);
    })
  );
});
