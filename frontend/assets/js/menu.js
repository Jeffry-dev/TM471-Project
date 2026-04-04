// menu.js

const API_BASE_URL = (window.APP_CONFIG && window.APP_CONFIG.API_BASE_URL) || 'http://localhost:8000';

function escapeHtml(str) {
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function normalizeText(value) {
  return String(value || '').trim().toLowerCase();
}

function groupByCategory(items) {
  return items.reduce((acc, item) => {
    const key = item.category || 'Other';
    (acc[key] = acc[key] || []).push(item);
    return acc;
  }, {});
}

function categoryOrderIndex(cat) {
  const order = [
    'Mezze',
    'Salads',
    'Grills',
    'Wraps & Sandwiches',
    'Main Dishes',
    'Desserts',
    'Beverages',
  ];
  const idx = order.indexOf(cat);
  return idx === -1 ? 999 : idx;
}

function renderFilters(categories, selected, onSelect) {
  const host = document.getElementById('menu-filters');
  if (!host) return;

  host.innerHTML = '';

  const mkBtn = (label, value) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = `category-pill ${selected === value ? 'category-pill--active' : ''}`;
    btn.textContent = label;
    btn.addEventListener('click', () => onSelect(value));
    return btn;
  };

  host.appendChild(mkBtn('All', 'all'));
  categories.forEach((c) => host.appendChild(mkBtn(c, c)));
}

function renderMenu(byCategory, displayedCategories) {
  const host = document.getElementById('menu-section');
  if (!host) return;

  host.innerHTML = '';

  if (!displayedCategories || displayedCategories.length === 0) {
    host.innerHTML = '<p class="text-muted-soft">No menu items match your filters.</p>';
    return;
  }

  displayedCategories.forEach((category) => {
    const section = document.createElement('section');
    section.className = 'surface-card rounded-2xl border border-subtle p-6 shadow-sm reveal';

    const header = document.createElement('div');
    header.className = 'mb-6 flex items-baseline justify-between gap-4';

    const h2 = document.createElement('h2');
    h2.className = 'text-xl font-bold tracking-tight text-[var(--accent)]';
    h2.textContent = category;

    const rule = document.createElement('div');
    rule.className = 'h-px flex-1 bg-gradient-to-r from-[var(--accent)] via-[hsla(175_80%_50%_/0.16)] to-transparent';

    header.appendChild(h2);
    header.appendChild(rule);
    section.appendChild(header);

    const grid = document.createElement('div');
    grid.className = 'grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';

    const items = byCategory[category] || [];
    items.forEach((item) => {
      const card = document.createElement('article');
      card.className = 'menu-card';

      const media = document.createElement('div');
      media.className = 'menu-card__media';

      const img = document.createElement('img');
      img.src = item.imageUrl || 'https://images.pexels.com/photos/262978/pexels-photo-262978.jpeg?auto=compress&cs=tinysrgb&w=800';
      img.alt = item.name || 'Menu item';
      media.appendChild(img);

      if (item.isAvailable === false) {
        const overlay = document.createElement('div');
        overlay.className = 'absolute inset-0 bg-black/25';
        media.appendChild(overlay);
      }

      const body = document.createElement('div');
      body.className = 'flex flex-1 flex-col justify-between gap-2 p-4';

      const top = document.createElement('div');
      top.className = 'flex items-start justify-between gap-3';

      const title = document.createElement('h3');
      title.className = 'text-base font-semibold tracking-tight';
      title.innerHTML = escapeHtml(item.name || '');

      top.appendChild(title);

      if (item.isAvailable === false) {
        const badge = document.createElement('span');
        badge.className = 'badge-unavailable rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide';
        badge.textContent = 'Unavailable';
        top.appendChild(badge);
      }

      body.appendChild(top);

      if (item.description) {
        const desc = document.createElement('p');
        desc.className = 'mt-2 line-clamp-3 text-xs leading-relaxed text-muted-soft';
        desc.innerHTML = escapeHtml(item.description);
        body.appendChild(desc);
      }

      const meta = document.createElement('div');
      meta.className = 'mt-3 flex items-center justify-between border-t border-subtle pt-3';

      const price = document.createElement('span');
      price.className = 'menu-card__price text-sm';
      price.textContent = `$${Number(item.price).toFixed(2)}`;

      meta.appendChild(price);
      body.appendChild(meta);

      card.appendChild(media);
      card.appendChild(body);

      grid.appendChild(card);
    });

    section.appendChild(grid);
    host.appendChild(section);
  });

  // Re-bind reveal observer for newly added nodes
  if (typeof window.bindRevealOnScroll === 'function') {
    window.bindRevealOnScroll();
  }
}

async function loadMenu() {
  const host = document.getElementById('menu-section');
  if (host) host.innerHTML = '<p class="text-muted-soft">Loading…</p>';

  const res = await fetch(`${API_BASE_URL}/menu`, { cache: 'no-store' });
  if (!res.ok) throw new Error(`Failed to fetch menu: ${res.status}`);

  return res.json();
}

document.addEventListener('DOMContentLoaded', async () => {
  try {
    const items = await loadMenu();
    const allItems = Array.isArray(items) ? items : [];
    const allByCategory = groupByCategory(allItems);
    const categories = Object.keys(allByCategory).sort((a, b) => categoryOrderIndex(a) - categoryOrderIndex(b));

    const searchInput = document.getElementById('menu-search');
    const summaryEl = document.getElementById('menu-results-summary');

    let selected = 'all';
    let searchQuery = '';

    const update = () => {
      const filteredItems = allItems.filter((item) => {
        if (!searchQuery) return true;
        return normalizeText(item.name).includes(searchQuery);
      });

      const filteredByCategory = groupByCategory(filteredItems);
      const displayed = selected === 'all'
        ? Object.keys(filteredByCategory).sort((a, b) => categoryOrderIndex(a) - categoryOrderIndex(b))
        : (filteredByCategory[selected] ? [selected] : []);

      renderFilters(categories, selected, (v) => {
        selected = v;
        update();
        const menuSection = document.getElementById('menu-section');
        if (menuSection) menuSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });

      renderMenu(filteredByCategory, displayed);

      if (summaryEl) {
        const total = filteredItems.length;
        if (searchQuery) {
          summaryEl.textContent = `${total} result${total === 1 ? '' : 's'} for “${searchQuery}”.`;
        } else {
          summaryEl.textContent = `Showing ${total} menu item${total === 1 ? '' : 's'}.`;
        }
      }
    };

    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        searchQuery = normalizeText(e.target.value);
        update();
      });
    }

    update();
  } catch (err) {
    console.warn(err);
    const host = document.getElementById('menu-section');
    if (host) {
      host.innerHTML = `
        <p class="text-muted-soft">
          Could not load menu items. Make sure your backend is running at
          <span class="text-[var(--text-main)]">${API_BASE_URL}</span>
          and that it allows requests from this site (CORS).
        </p>
      `;
    }
  }
});
