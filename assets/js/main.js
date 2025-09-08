
$(document).ready(function () {
  setTimeout(() => {
    const url = `https://2smartblog.vercel.app/api/blogs`;

    // ---------- STATE ----------
    // Estrutura de estado por aba: { activeTab: string, pages: { [paneId]: number } }
    const STATE_KEY = 'newsTabsState';
    const tabControllers = new Map(); // key: paneId, value: { show(p), page, total }
    let state = loadState() || { activeTab: 'todos', pages: {} };

    // Util: ler/gravar estado
    function saveState(pushHistory = true) {
      try { sessionStorage.setItem(STATE_KEY, JSON.stringify(state)); } catch (_) {}
      // guarda em URL sem recarregar (facilita "voltar")
      if (pushHistory) {
        const urlObj = new URL(window.location.href);
        urlObj.searchParams.set('tab', state.activeTab);
        const page = state.pages[state.activeTab] || 1;
        urlObj.searchParams.set('page', String(page));
        history.pushState({ tab: state.activeTab, page }, '', urlObj.toString());
      }
    }
    function loadState() {
      try { return JSON.parse(sessionStorage.getItem(STATE_KEY) || ''); } catch (_) { return null; }
    }

    // Restaura a partir do URL (permite partilhar link direto ou voltar)
    function initStateFromURL() {
      const urlObj = new URL(window.location.href);
      const tab = urlObj.searchParams.get('tab');
      const page = parseInt(urlObj.searchParams.get('page') || '1', 10);
      if (tab) {
        state.activeTab = tab;
        if (Number.isFinite(page) && page > 0) state.pages[tab] = page;
      }
    }
    initStateFromURL();

    // popstate (botão voltar/avançar do browser)
    window.addEventListener('popstate', (ev) => {
      const tab = ev.state?.tab || state.activeTab || 'todos';
      const page = ev.state?.page || state.pages[tab] || 1;
      state.activeTab = tab;
      state.pages[tab] = page;
      setActiveTab(tab, /*pushHistory*/ false);
    });

    // ---------- FETCH ----------
    $.ajax({
      url,
      method: 'GET',
      success: function (response) {
        if (response?.status !== "ok") return;

        const validArticles = (response.articles || []).filter(a => a?.urlToImage);
        if (!validArticles.length) return;

        insertLatestThreePosts(validArticles.slice(0, 3));
        insertLatestNews(validArticles[0]);

        const allArticles = validArticles.slice(1);

        const grouped = allArticles.reduce((acc, a) => {
          const cat = a?.category || 'Outros';
          (acc[cat] ||= []).push(a);
          return acc;
        }, {});

        buildTabsAndContent(grouped, allArticles);
        setupTabSwitching();
        setupExternalToggles(); // <— trata dos botões/links que abrem categorias

        // Abre a aba inicial (vinda do URL ou do storage)
        setActiveTab(state.activeTab || 'todos', /*pushHistory*/ false);
      },
      error: function (xhr, status, error) {
        console.error("Erro ao buscar notícias:", error);
      }
    });

    // ---------- RENDER TOP ----------
    function insertLatestNews(article) {
      const image = article.urlToImage || 'fallback.jpg';
      const title = article.title || 'Título indisponível';
      const date = formatDate(article.publishedAt);
      const desc = truncateText(article.short_description || article.content || '', 250);
      const source = article.category || 'Notícia';

      const html = `
        <div class="image-area">
          <a href="blog-details.html?title=${encodeURIComponent(title)}">
            <img src="${image}" alt="">
          </a>
        </div>
        <div class="content-box mr_80">
          <div class="sec-title pb_20 sec-title-animation animation-style2">
            <span class="sub-title mb_10 title-animationx">${source}</span>
            <a href="blog-details.html?title=${encodeURIComponent(title)}">
              <h3 class="title-animationx">${title}</h3>
            </a>
            <br>
            <strong>${date}</strong>
          </div>
          <div class="text-box">
            <p>${desc}</p>
          </div>
        </div>`;
      $('.blog-latest').html(html);
    }

    function insertLatestThreePosts(articles) {
      const $container = $('#latest-tree-posts');
      $container.empty();
      articles.forEach(a => {
        const html = `
        <div class="col-lg-4 col-md-6 col-sm-12 news-block">
          <div class="news-block-two wow fadeInUp animated" data-wow-delay="00ms" data-wow-duration="1500ms">
            <div class="inner-box">
              <div class="image-box" style="max-height:410px; overflow:hidden;">
                <figure class="image" style="width:100%; height:230px; object-fit:cover;">
                  <a href="blog-details.html?title=${encodeURIComponent(a.title)}">
                    <img class="blurhash-auto" src="${a.urlToImage}" alt="${a.title}" style="width:100%; height:auto; min-height:400px; max-height:410px; object-fit:cover;">
                  </a>
                </figure>
              </div>
              <div class="lower-content">
                <span class="category">${a.category || 'Notícia'}</span>
                <h3><a href="blog-details.html?title=${encodeURIComponent(a.title)}">${truncateText(a.title, 45)}</a></h3>
                <p>${truncateText(a.short_description || a.content || '', 60)}</p>
                <br/>
                <ul class="post-info">
                  <li><strong>${formatDate(a.publishedAt)}</strong></li>
                </ul>
              </div>
            </div>
          </div>
        </div>`;
        $container.append(html);
      });
    }

    // ---------- TABS ----------
    function buildTabsAndContent(grouped, allArticles) {
      const tabList = $('#pills-tab');
      const tabContent = $('#pills-tabContent');

      tabList.empty();
      tabContent.empty();

      // Aba "Todas"
      tabList.append(`
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-tab="todos" type="button" role="tab" aria-selected="false">Todas</button>
        </li>`);

      tabContent.append(`
        <div class="tab-pane" id="todos">
          <div class="row clearfix">
            <div class="col-md-12 content-side">
              <div class="blog-grid-content pagination-content-area">
                <div class="row clearfix" id="content-todos"></div>
                <div class="pagination-wrapper">
                  <ul class="pagination clearfix" id="pagination-todos"></ul>
                </div>
              </div>
            </div>
          </div>
        </div>`);

      createRenderController('todos', '#content-todos', '#pagination-todos', allArticles);

      // Categorias
      let idx = 0;
      Object.entries(grouped).forEach(([cat, arts]) => {
        const paneId = `pane-${idx}`;

        tabList.append(`
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-tab="${paneId}" type="button" role="tab" aria-selected="false">${cat}</button>
          </li>`);

        tabContent.append(`
          <div class="tab-pane d-none" id="${paneId}">
            <div class="row clearfix">
              <div class="col-md-12 content-side">
                <div class="blog-grid-content pagination-content-area">
                  <div class="row clearfix" id="content-${paneId}"></div>
                  <div class="pagination-wrapper">
                    <ul class="pagination clearfix" id="pagination-${paneId}"></ul>
                  </div>
                </div>
              </div>
            </div>
          </div>`);

        createRenderController(paneId, `#content-${paneId}`, `#pagination-${paneId}`, arts);
        idx++;
      });
    }

    // Controller por aba com preservação de página + atualização de estado
    function createRenderController(paneId, containerSel, paginationSel, articles) {
      const $container = $(containerSel);
      const $pagination = $(paginationSel);
      const perPage = 6;
      const total = Math.max(1, Math.ceil(articles.length / perPage));
      let page = clamp(state.pages[paneId] || 1, 1, total);

      function drawPage(p, persist = true) {
        page = clamp(p, 1, total);

        // Atualiza estado desta aba
        state.pages[paneId] = page;
        if (persist && state.activeTab === paneId) saveState(/*pushHistory*/ true);

        $container.empty();

        const start = (page - 1) * perPage;
        const end = page * perPage;
        articles.slice(start, end).forEach(a => {
          $container.append(`
            <div class="col-lg-4 col-md-4 col-sm-12 news-block">
              <div class="news-block-two wow fadeInUp animated">
                <div class="inner-box">
                  <div class="image-box">
                    <figure class="image">
                      <a href="blog-details.html?title=${encodeURIComponent(a.title)}">
                        <img src="${a.urlToImage}" alt="">
                      </a>
                    </figure>
                  </div>
                  <div class="lower-content">
                    <span class="category">${a.category || 'Notícia'}</span>
                    <h3><a href="blog-details.html?title=${encodeURIComponent(a.title)}">${truncateText(a.title, 50)}</a></h3>
                    <p class="news-description">${truncateText(a.short_description || a.content || '', 60)}</p>
                    <ul class="post-info">
                      <br/><br/>
                      <li><strong>${formatDate(a.publishedAt)}</strong></li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>`);
        });

        // Paginação (recriada, mas permanece no DOM da aba — não se perde ao alternar)
        $pagination.empty();

        const $left = $(`<li class="left-arrow"><a href="#"${page === 1 ? ' class="disabled"' : ''}><i class="icon-34"></i></a></li>`);
        $left.off('click').on('click', (e) => { e.preventDefault(); if (page > 1) drawPage(page - 1); });
        $pagination.append($left);

        for (let i = 1; i <= total; i++) {
          const $li = $(`<li><a href="#"${i === page ? ' class="current"' : ''}>${i}</a></li>`);
          $li.off('click').on('click', (e) => { e.preventDefault(); drawPage(i); });
          $pagination.append($li);
        }

        const $right = $(`<li class="right-arrow"><a href="#"${page === total ? ' class="disabled"' : ''}><i class="icon-35"></i></a></li>`);
        $right.off('click').on('click', (e) => { e.preventDefault(); if (page < total) drawPage(page + 1); });
        $pagination.append($right);
      }

      // expõe o controller desta aba
      tabControllers.set(paneId, {
        get page() { return page; },
        get total() { return total; },
        show: (p, persist = false) => drawPage(p ?? page, persist),
      });
    }

    // Alterna de aba sem destruir markup/paginação; re-renderiza a página atual
    function setActiveTab(paneId, pushHistory = true) {
      if (!paneId || !tabControllers.has(paneId)) paneId = 'todos';

      // Botões
      $('#pills-tab button.nav-link').removeClass('active').attr('aria-selected', 'false');
      $(`#pills-tab button.nav-link[data-tab="${paneId}"]`).addClass('active').attr('aria-selected', 'true');

      // Painéis (só esconder/mostrar)
      $('#pills-tabContent .tab-pane').addClass('d-none');
      const $pane = $(`#${paneId}`);
      $pane.removeClass('d-none');

      // Estado
      state.activeTab = paneId;
      const ctrl = tabControllers.get(paneId);
      const desiredPage = clamp(state.pages[paneId] || ctrl.page || 1, 1, ctrl.total);

      // Reapresenta a página atual daquela aba (sem reset)
      ctrl.show(desiredPage, /*persist*/ false);

      // Persistência (URL + sessionStorage)
      saveState(pushHistory);
    }

    // Clicks nas tabs (UI principal)
    function setupTabSwitching() {
      $('#pills-tab').off('click', 'button.nav-link').on('click', 'button.nav-link', function () {
        const selectedTab = String($(this).data('tab') || 'todos');
        setActiveTab(selectedTab, /*pushHistory*/ true);
      });
    }

    // Qualquer toggle externo que abra uma categoria específica:
    // basta adicionar data-goto-tab="pane-X" no botão/link.
    function setupExternalToggles() {
      $(document).off('click', '[data-goto-tab]').on('click', '[data-goto-tab]', function (e) {
        e.preventDefault();
        const target = String($(this).data('goto-tab') || '');
        if (target) setActiveTab(target, /*pushHistory*/ true);
      });
    }

    // ---------- DETALHE (página blog-details) ----------
    (function initDetails() {
      const params = new URLSearchParams(window.location.search);
      const titleParam = params.get('title') ? decodeURIComponent(params.get('title')) : null;

      if (!titleParam) {
        // estamos listagem (não é detalhe)
        return;
      }

      $.get(url, function (response) {
        if (response?.status !== 'ok') return;

        const found = (response.articles || []).find(article => article.title === titleParam);
        if (!found) {
          $('.details-blog').html('<p>Notícia não encontrada.</p>');
          return;
        }

        renderDetails(found);

        const otherArticles = response.articles
          .filter(a => a.title !== found.title && a.urlToImage)
          .slice(0, 2);
        renderMoreNews(otherArticles);
      });

      function renderMoreNews(articles) {
        const $container = $('#more-news');
        $container.empty();
        articles.forEach(a => {
          const html = `
            <article>
              <a href="blog-details.html?title=${encodeURIComponent(a.title)}">
                <img src="${a.urlToImage}" alt="${a.title}">
              </a>
              <div class="block-description">
                <a href="blog-details.html?title=${encodeURIComponent(a.title)}">
                  <h5>${truncateText(a.title, 40)}</h5>
                </a>
                <span class="text-primary">${formatDate(a.publishedAt)}</span>
              </div>
            </article>`;
          $container.append(html);
        });
      }

      function renderDetails(article) {
        const formattedDate = formatDate(article.publishedAt);
        const source = article.category || 'Notícia';
        const title = article.title || '';
        const image = article.urlToImage || '';

        $('meta[property="og:title"]').attr("content", title);
        $('meta[property="og:description"]').attr("content", article.description || '');
        $('meta[property="og:image"]').attr("content", image);

        const html = `
          <div class="inner-box">
            <div class="lower-content">
              <span class="category">${source}</span>
              <h3>${title}</h3>
              <ul class="post-info"><li><span>${formattedDate}</span></li></ul>
            </div>
            <div class="text-box pt_25 mb_0">
              <div class="mb_30">${article.description || ''}</div>
              <br>
            </div>
          </div>`;
        $('#details-blog').html(html);
        if (typeof RemoveWhiteSpace === 'function') RemoveWhiteSpace();
      }
    })();

    // ---------- Utils ----------
    function truncateText(text, max) {
      return text?.length > max ? text.slice(0, max) + '...' : (text || '');
    }
    function formatDate(dt) {
      return new Date(dt).toLocaleDateString('pt-PT', {
        day: '2-digit', month: 'long', year: 'numeric'
      });
    }
    function clamp(n, min, max) {
      return Math.max(min, Math.min(max, Number.isFinite(n) ? n : min));
    }
  }, 1500);
});


























document.addEventListener("DOMContentLoaded", function () {
  let megaMenus = document.querySelectorAll(".mega-menu");

  megaMenus.forEach((MegaMenu) => {
    let menusToggles = MegaMenu.querySelectorAll(".formation-box h5");
    let menuItems = MegaMenu.querySelectorAll(".menu-options menu");

    if (!menusToggles.length || !menuItems.length) return;

    // primeiro item como "default"
    let firstToggle = menusToggles[0];
    let firstItem = menuItems[0];

    function activateItem(toggle, item) {
      let activeToggle = MegaMenu.querySelector(".formation-box h5.active");
      let activeItem = MegaMenu.querySelector(".menu-options menu.active");
      if (activeToggle) activeToggle.classList.remove("active");
      if (activeItem) activeItem.classList.remove("active");

      toggle.classList.add("active");
      item.classList.add("active");
    }

    // Ativar o primeiro item logo no início
    activateItem(firstToggle, firstItem);

    // 🔑 delegação de eventos
    MegaMenu.addEventListener("mouseover", function (e) {
      let toggle = e.target.closest(".formation-box h5");
      if (!toggle) return;

      let index = Array.from(menusToggles).indexOf(toggle);
      if (index >= 0) {
        activateItem(toggle, menuItems[index]);
      }
    });

    MegaMenu.addEventListener("mouseleave", () => {
      activateItem(firstToggle, firstItem);
    });
  });
});



/*** Translator code */

function googleTranslateElementInit() {
  new google.translate.TranslateElement({
    pageLanguage: 'pt',
    includedLanguages: 'fr,es,en,pt',
    autoDisplay: false
  }, 'google_translate_element');
}

(function loadGTranslate() {
  const s = document.createElement('script');
  s.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
  document.head.appendChild(s);
})();


const translations = {
  /** Home page text to translate */
  "t1": {
    "pt": `Inicio`,
    "en": `Home`,
    "es": `Inicio`,
    "fr": `Accueil`
  },
  "t2": {
    "pt": `Your app powered by AI`,
    "en": `Your app powered by AI`,
    "es": `Tu aplicación impulsada por IA`,
    "fr": `Votre application propulsée par l'IA`
  },
  "t3": {
    "pt": "2Smart Software de",
    "en": "2Smart Management Software",
    "es": "2Smart Software de",
    "fr": "2Smart Logiciel de"
  },
  "t4": {
    "pt": " gestão de assiduiades",
    "en": " Attendance ",
    "es": " gestión de asistencias",
    "fr": " gestion des présences"
  },
  "t5": {
    "pt": ` a sua solução de RH`,
    "en": `your HR solution`,
    "es": `su solución de RRHH`,
    "fr": `votre solution RH`
  },
  "t6": {
    "pt": `+650 empresas e +30.800 colaboradores`,
    "en": `+650 companies and +30,800 employees`,
    "es": `+650 empresas y +30.800 empleados`,
    "fr": `+650 entreprises et +30 800 collaborateurs`
  },
  "t7": {
    "pt": `usam o nosso software`,
    "en": `use our software`,
    "es": `usan nuestro software`,
    "fr": `utilisent notre logiciel`
  },
  "t8": {
    "pt": `Sobre o 2Smart HR`,
    "en": `About 2Smart HR`,
    "es": `Sobre 2Smart HR`,
    "fr": `À propos de 2Smart HR`
  },
  "t9": {
    "pt": ` Simplifique a sua Gestão de Recursos Humanos com o  <span>2Smart</span>`,
    "en": `Simplify your Human Resources Management with  <span>2Smart</span>`,
    "es": `Simplifique su gestión de recursos humanos con  <span>2Smart</span>`,
    "fr": `Simplifiez votre gestion des ressources humaines avec  <span>2Smart</span>`
  },
  "t10": {
    "pt": `Gestão de Feriados e Tolerâncias`,
    "en": `Holiday and Allowance Management`,
    "es": `Gestión de Festivos y Tolerancias`,
    "fr": `Gestion des congés et tolérances`
  },
  "t11": {
    "pt": `Domínio Personalizado para  Empresas`,
    "en": `Custom Domain for Companies`,
    "es": `Dominio Personalizado para Empresas`,
    "fr": `Domaine personnalisé pour les entreprises`
  },
  "t12": {
    "pt": ` Análise de Produtividade`,
    "en": `Productivity Analysis`,
    "es": `Análisis de Productividad`,
    "fr": `Analyse de la productivité`
  },
  "t13": {
    "pt": ` Planificação & Férias`,
    "en": `Planning & Vacations`,
    "es": `Planificación y Vacaciones`,
    "fr": `Planification & Congés`
  },
  "t14": {
    "pt": ` Relatórios Personalizados`,
    "en": `Custom Reports`,
    "es": `Informes Personalizados`,
    "fr": `Rapports personnalisés`
  },
  "t15": {
    "pt": ` Exportação Inteligente `,
    "en": `Smart Export`,
    "es": `Exportación Inteligente`,
    "fr": `Exportation intelligente`
  },
  "t16": {
    "pt": ` Os melhores recursos `,
    "en": `The best features`,
    "es": `Los mejores recursos`,
    "fr": `Les meilleures fonctionnalités`
  },
  "t17": {
    "pt": ` A app  2Smart  HR oferece um conjunto poderoso de ferramentas que tornam a gestão de
    assiduidade e RH mais simples, eficiente e totalmente automatizada – tudo ao seu
    alcance, em qualquer lugar. `,
    "en": `The 2Smart HR app offers a powerful set of tools that make attendance and HR management simpler, more efficient, and fully automated – all within your reach, anywhere.`,
    "es": `La aplicación 2Smart HR ofrece un potente conjunto de herramientas que hacen que la gestión de asistencia y RRHH sea más simple, eficiente y totalmente automatizada, al alcance de su mano en cualquier lugar.`,
    "fr": `L'application 2Smart HR offre un ensemble puissant d'outils qui rendent la gestion des présences et des RH plus simple, plus efficace et totalement automatisée – à portée de main, partout.`
  },
  "t18": {
    "pt": `Destaques`,
    "en": `Highlights`,
    "es": `Destacados`,
    "fr": `Points forts`
  },
  "t19": {
    "pt": `Elementos Diferenciadores do 2Smart`,
    "en": `Differentiating Features of 2Smart`,
    "es": `Elementos Diferenciadores de 2Smart`,
    "fr": `Éléments différenciateurs de 2Smart`
  },
  "t20": {
    "pt": `  Setores`,
    "en": `Sectors`,
    "es": `Sectores`,
    "fr": `Secteurs`
  },
  "t21": {
    "pt": ` Setores Atendidos`,
    "en": `Served Sectors`,
    "es": `Sectores Atendidos`,
    "fr": `Secteurs desservis`
  },
  "t22": {
    "pt": ` Workflow  `,
    "en": `Workflow`,
    "es": `Flujo de trabajo`,
    "fr": `Flux de travail`
  },
  "t23": {
    "pt": ` Estruturas da Empresa`,
    "en": `Company Structures`,
    "es": `Estructuras de la Empresa`,
    "fr": `Structures de l'entreprise`
  },
  "t24": {
    "pt": ` Testemunhos  `,
    "en": `Testimonials`,
    "es": `Testimonios`,
    "fr": `Témoignages`
  },
  "t25": {
    "pt": `Testemunhos mais recentes`,
    "en": `Latest Testimonials`,
    "es": `Testimonios más recientes`,
    "fr": `Derniers témoignages`
  },
  "t26": {
    "pt": `Gestão Inteligente`,
    "en": `Smart Management`,
    "es": `Gestión Inteligente`,
    "fr": `Gestion intelligente`
  },
  "t27": {
    "pt": `Geofencing para Equipas e Projetos`,
    "en": `Geofencing for Teams and Projects`,
    "es": `Geocercas para Equipos y Proyectos`,
    "fr": `Géorepérage pour équipes et projets`
  },
  "t28": {
    "pt": `Sistemas ERP`,
    "en": `ERP Systems`,
    "es": `Sistemas ERP`,
    "fr": `Systèmes ERP`
  },
  "t29": {
    "pt": `Powerful Integration`,
    "en": `Powerful Integration`,
    "es": `Integración Potente`,
    "fr": `Intégration Puissante`
  },
  "t30": {
    "pt": `Formações & Apresentações`,
    "en": `Trainings & Presentations`,
    "es": `Formaciones y Presentaciones`,
    "fr": `Formations & Présentations`
  },
  "t31": {
    "pt": `Eventos Recentes`,
    "en": `Recent Events`,
    "es": `Eventos Recientes`,
    "fr": `Événements récents`
  },
  "t32": {
    "pt": `  `,
    "en": ``,
    "es": ``,
    "fr": ``
  },
  "t33": {
    "pt": `  `,
    "en": ``,
    "es": ``,
    "fr": ``
  },
  "t34": {
    "pt": `  `,
    "en": ``,
    "es": ``,
    "fr": ``
  },
  "t35": {
    "pt": `  `,
    "en": ``,
    "es": ``,
    "fr": ``
  },


  /*** ABOUT */
  "t36": {
    "pt": `2Smart HR`,
    "en": `2Smart HR`,
    "es": `2Smart HR`,
    "fr": `2Smart HR`
  },"t37": {
  "pt": ` A solução <span>definitiva</span> para sua <div class="underline"> empresa</div>`,
  "en": ` The <span>definitive</span> solution for your <div class="underline">company</div>`,
  "es": ` La solución <span>definitiva</span> para su <div class="underline">empresa</div>`,
  "fr": ` La solution <span>définitive</span> pour votre <div class="underline">entreprise</div>`
},
"t38": {
  "pt": ` +650 empresas e +30.800 colaboradores <br> usam o nosso software`,
  "en": ` +650 companies and +30,800 employees <br> use our software`,
  "es": ` +650 empresas y +30.800 empleados <br> utilizan nuestro software`,
  "fr": ` +650 entreprises et +30.800 collaborateurs <br> utilisent notre logiciel`
},
"t39": {
  "pt": `Testemunhos`,
  "en": `Testimonials`,
  "es": `Testimonios`,
  "fr": `Témoignages`
},
"t40": {
  "pt": `Testemunhos mais recentes`,
  "en": `Latest testimonials`,
  "es": `Testimonios más recientes`,
  "fr": `Témoignages récents`
},
"t41": {
  "pt": `2Smart: simplifique o RH, <span>potencialize</span> as equipas`,
  "en": `2Smart: simplify HR, <span>empower</span> teams`,
  "es": `2Smart: simplifique RRHH, <span>potencie</span> los equipos`,
  "fr": `2Smart : simplifiez les RH, <span>renforcez</span> les équipes`
},
"t42": {
  "pt": `Formações & Apresentações`,
  "en": `Trainings & Presentations`,
  "es": `Formaciones y Presentaciones`,
  "fr": `Formations & Présentations`
},
"t43": {
  "pt": `Eventos Recentes`,
  "en": `Recent Events`,
  "es": `Eventos Recientes`,
  "fr": `Événements récents`
},

/*** SL - Aplicação  */
"t44": {
  "pt": `Gestão de horários e assiduidade na palma da  mão`,
  "en": `Schedule and attendance management in the palm of your hand`,
  "es": `Gestión de horarios y asistencia en la palma de tu mano`,
  "fr": `Gestion des horaires et de l’assiduité au creux de votre main`
},
"t45": {
  "pt": `Aplicação <span translate="no">2Smart</span>`,
  "en": `<span translate="no">2Smart</span> App`,
  "es": `Aplicación <span translate="no">2Smart</span>`,
  "fr": `Application <span translate="no">2Smart</span>`
},
"t46": {
  "pt": `Gestão de RH na palma da mão`,
  "en": `HR management in the palm of your hand`,
  "es": `Gestión de RRHH en la palma de tu mano`,
  "fr": `Gestion des RH au creux de votre main`
}, 
"t47": {
  "pt": `2Smart App para colaboradores`,
  "en": `2Smart App for employees`,
  "es": `App 2Smart para empleados`,
  "fr": `Application 2Smart pour les collaborateurs`
},
"t48": {
  "pt": `Mobilidade e Gestão   Inteligente`,
  "en": `Mobility and Smart Management`,
  "es": `Movilidad y Gestión Inteligente`,
  "fr": `Mobilité et Gestion Intelligente`
},
"t49": {
  "pt": `A App 2Smart em Tempo Real`,
  "en": `The 2Smart App in Real Time`,
  "es": `La App 2Smart en Tiempo Real`,
  "fr": `L’application 2Smart en Temps Réel`
},



/** Blogue */

"t50": {
  "pt": `Acompanhe as inovações e resultados que transformam a gestão de pessoas`,
  "en": `Follow the innovations and results that are transforming people management`,
  "es": `Siga las innovaciones y resultados que están transformando la gestión de personas`,
  "fr": `Suivez les innovations et les résultats qui transforment la gestion des personnes`
},

/** Pagina de contctos */
 "t51": {
  "pt": `Contacte-nos`,
  "en": `Contact us`,
  "es": `Contáctenos`,
  "fr": `Contactez-nous`
},
"t52": {
  "pt": ` 2Smart HR - Power to You!`,
  "en": ` 2Smart HR - Power to You!`,
  "es": ` 2Smart HR - ¡Poder para ti!`,
  "fr": ` 2Smart HR - Le pouvoir est à vous !`
},
"t53": {
  "pt": `informação geral`,
  "en": `general information`,
  "es": `información general`,
  "fr": `informations générales`
},
"t54": {
  "pt": `suporte técnico`,
  "en": `technical support`,
  "es": `soporte técnico`,
  "fr": `support technique`
},
"t55": {
  "pt": ` +650 empresas e +30.800 colaboradores <br> usam o nosso software`,
  "en": ` +650 companies and +30,800 employees <br> use our software`,
  "es": ` +650 empresas y +30.800 empleados <br> utilizan nuestro software`,
  "fr": ` +650 entreprises et +30.800 collaborateurs <br> utilisent notre logiciel`
},
"t56": {
  "pt": `Localizações`,
  "en": `Locations`,
  "es": `Ubicaciones`,
  "fr": `Localisations`
},
"t57": {
  "pt": `Saiba onde estamos localizados`,
  "en": `Find out where we are located`,
  "es": `Descubra dónde estamos ubicados`,
  "fr": `Découvrez où nous sommes situés`
},
"t58": {
  "pt": `locais diferentes mas com o mesmo objectivo`,
  "en": `different places but with the same goal`,
  "es": `lugares diferentes pero con el mismo objetivo`,
  "fr": `lieux différents mais avec le même objectif`
},
"t59": {
  "pt": `Formações & Apresentações`,
  "en": `Trainings & Presentations`,
  "es": `Formaciones y Presentaciones`,
  "fr": `Formations & Présentations`
},
"t60": {
  "pt": `Eventos Recentes`,
  "en": `Recent Events`,
  "es": `Eventos Recientes`,
  "fr": `Événements récents`
},

/** SL - Gestão de colaboradores */
"t61": {
  "pt": `Soluções modernas para RH`,
  "en": `Modern solutions for HR`,
  "es": `Soluciones modernas para RRHH`,
  "fr": `Solutions modernes pour les RH`
},
"t62": {
  "pt": `Gestão de Colaboradores`,
  "en": `Employee Management`,
  "es": `Gestión de Empleados`,
  "fr": `Gestion des Collaborateurs`
},
"t63": {
  "pt": `Acesso Rápido e   Autónomo`,
  "en": `Fast and Autonomous Access`,
  "es": `Acceso Rápido y Autónomo`,
  "fr": `Accès Rapide et Autonome`
},
"t64": {
  "pt": `Gestão em Tempo Real`,
  "en": `Real-Time Management`,
  "es": `Gestión en Tiempo Real`,
  "fr": `Gestion en Temps Réel`
},
"t65": {
  "pt": `Férias Pendentes, Faltas por Classificar e Localização`,
  "en": `Pending Vacations, Unclassified Absences and Location`,
  "es": `Vacaciones Pendientes, Ausencias por Clasificar y Localización`,
  "fr": `Congés en Attente, Absences à Classer et Localisation`
},

/** SL -  ERP */
"t66": {
  "pt": `Gestão de assiduidade, horários e integração ERP simplificada`,
  "en": `Attendance, schedule management and simplified ERP integration`,
  "es": `Gestión de asistencia, horarios e integración ERP simplificada`,
  "fr": `Gestion de l’assiduité, des horaires et intégration ERP simplifiée`
},
"t67": {
  "pt": `Exportação ERP`,
  "en": `ERP Export`,
  "es": `Exportación ERP`,
  "fr": `Exportation ERP`
},
"t68": {
  "pt": `Exportação ERP automatizada`,
  "en": `Automated ERP Export`,
  "es": `Exportación ERP automatizada`,
  "fr": `Exportation ERP automatisée`
},
"t69": {
  "pt": `2Smart SaaS: RH e Assiduidade  Integrados`,
  "en": `2Smart SaaS: Integrated HR and Attendance`,
  "es": `2Smart SaaS: RRHH y Asistencia Integrados`,
  "fr": `2Smart SaaS : RH et Assiduité Intégrés`
},
"t70": {
  "pt": `Exportação ERP e Gestão  Inteligente`,
  "en": `ERP Export and Smart Management`,
  "es": `Exportación ERP y Gestión Inteligente`,
  "fr": `Exportation ERP et Gestion Intelligente`
},
"t71": {
  "pt": `2Smart SaaS em Tempo Real`,
  "en": `2Smart SaaS in Real Time`,
  "es": `2Smart SaaS en Tiempo Real`,
  "fr": `2Smart SaaS en Temps Réel`
},

/** SL - Geofencing */

"t72": {
  "pt": "Tecnologia inteligente ao serviço das equipas",
  "en": "Smart technology at the service of teams",
  "es": "Tecnología inteligente al servicio de los equipos",
  "fr": "Technologie intelligente au service des équipes"
}, 
"t73": {
  "pt": "Geofencing e Geolocalização",
  "en": "Geofencing and Geolocation",
  "es": "Geofencing y Geolocalización",
  "fr": "Géorepérage et géolocalisation"
},
"t74": {
  "pt": "Presença Inteligente",
  "en": "Smart Presence",
  "es": "Presencia Inteligente",
  "fr": "Présence Intelligente"
},
"t75": {
  "pt": "Geofencing na Gestão de Equipas",
  "en": "Geofencing in Team Management",
  "es": "Geofencing en la Gestión de Equipos",
  "fr": "Géorepérage dans la Gestion des Équipes"
},
"t76": {
  "pt": "Tecnologia Aplicada à Mobilidade",
  "en": "Technology Applied to Mobility",
  "es": "Tecnología Aplicada a la Movilidad",
  "fr": "Technologie Appliquée à la Mobilité"
},
"t77": {
  "pt": "Controlo de Localização em Tempo Real",
  "en": "Real-Time Location Tracking",
  "es": "Control de Ubicación en Tiempo Real",
  "fr": "Contrôle de Localisation en Temps Réel"
},


  /** SL - Planning and workflow  */
 
"t78": {
  "pt": "Tecnologia inteligente ao serviço das equipas",
  "en": "Smart technology at the service of teams",
  "es": "Tecnología inteligente al servicio de los equipos",
  "fr": "Technologie intelligente au service des équipes"
},
"t79": {
  "pt": "Planificação e Workflow",
  "en": "Planning and Workflow",
  "es": "Planificación y Flujo de Trabajo",
  "fr": "Planification et Flux de Travail"
},
"t80": {
  "pt": "Planeamento Inteligente",
  "en": "Smart Planning",
  "es": "Planificación Inteligente",
  "fr": "Planification Intelligente"
},
"t81": {
  "pt": "Mapas e Horários de Trabalho",
  "en": "Work Maps and Schedules",
  "es": "Mapas y Horarios de Trabajo",
  "fr": "Cartes et Horaires de Travail"
},
"t82": {
  "pt": "Organização Estrutural",
  "en": "Structural Organization",
  "es": "Organización Estructural",
  "fr": "Organisation Structurelle"
},
"t83": {
  "pt": "Gestão de Equipa Facilitada",
  "en": "Simplified Team Management",
  "es": "Gestión de Equipo Facilitada",
  "fr": "Gestion d'Équipe Facilitée"
},
"t84": {
  "pt": "Mapas de Férias e Workflow",
  "en": "Holiday Maps and Workflow",
  "es": "Mapas de Vacaciones y Flujo de Trabajo",
  "fr": "Cartes de Vacances et Flux de Travail"
},

/** Prices */
"t85": {
  "pt": "Gestão de Assiduidades e equipas desde <br><span class='price-box' >0,75€ / mês</span> por colaborador",
  "en": "Attendance and team management from <br><span class='price-box'>€0.75 / month</span> per employee",
  "es": "Gestión de asistencias y equipos desde <br><span>0,75€ / mes</span> por empleado",
  "fr": "Gestion des présences et des équipes à partir de <br><span class='price-box'>0,75€ / mois</span> par collaborateur"
},
"t86": {
  "pt": "Gestão Centralizada em Cloud com Terminais Integrados",
  "en": "Centralized Cloud Management with Integrated Terminals",
  "es": "Gestión Centralizada en la Nube con Terminales Integrados",
  "fr": "Gestion Centralisée dans le Cloud avec Terminaux Intégrés"
},
"t87": {
  "pt": "Tudo o que precisa para gerir <br> a sua força de trabalho",
  "en": "Everything you need to manage <br> your workforce",
  "es": "Todo lo que necesita para gestionar <br> su fuerza laboral",
  "fr": "Tout ce dont vous avez besoin pour gérer <br> votre main-d'œuvre"
},
"t88": {
  "pt": "Gestão completa de assiduidade e equipas, colaborador a colaborador",
  "en": "Complete attendance and team management, employee by employee",
  "es": "Gestión completa de asistencia y equipos, empleado por empleado",
  "fr": "Gestion complète des présences et des équipes, collaborateur par collaborateur"
},
"t89": {
  "pt": "Dados em tempo real para decisões mais inteligentes",
  "en": "Real-time data for smarter decisions",
  "es": "Datos en tiempo real para decisiones más inteligentes",
  "fr": "Données en temps réel pour des décisions plus intelligentes"
},
"t90": {
  "pt": "Automatize processos de RH com fluxos personalizados",
  "en": "Automate HR processes with customized workflows",
  "es": "Automatice procesos de RRHH con flujos personalizados",
  "fr": "Automatisez les processus RH avec des flux personnalisés"
},
"t91": {
  "pt": "Registo de ponto digital, físico ou mobile — adaptado ao seu contexto",
  "en": "Digital, physical, or mobile time tracking — adapted to your context",
  "es": "Registro de horario digital, físico o móvil — adaptado a su contexto",
  "fr": "Enregistrement du temps digital, physique ou mobile — adapté à votre contexte"
},


/** SL - reports and analitycs */

"t92": {
  "pt": "Tecnologia inteligente ao serviço das equipas",
  "en": "Smart technology at the service of teams",
  "es": "Tecnología inteligente al servicio de los equipos",
  "fr": "Technologie intelligente au service des équipes"
},
"t93": {
  "pt": "Relatórios e Análises",
  "en": "Reports and Analysis",
  "es": "Informes y Análisis",
  "fr": "Rapports et Analyses"
},
"t94": {
  "pt": "Análise Inteligente de Dados",
  "en": "Smart Data Analysis",
  "es": "Análisis Inteligente de Datos",
  "fr": "Analyse Intelligente des Données"
},
"t95": {
  "pt": "Relatórios de Colaboradores e Operações",
  "en": "Employee and Operations Reports",
  "es": "Informes de Empleados y Operaciones",
  "fr": "Rapports sur les Collaborateurs et les Opérations"
},
"t96": {
  "pt": "Gestão de Recursos Facilitada",
  "en": "Simplified Resource Management",
  "es": "Gestión de Recursos Facilitada",
  "fr": "Gestion des Ressources Facilitée"
},
"t97": {
  "pt": "Relatórios de Férias, Baixas e Ausências",
  "en": "Reports on Holidays, Sick Leave, and Absences",
  "es": "Informes de Vacaciones, Bajas y Ausencias",
  "fr": "Rapports sur les Congés, Arrêts et Absences"
},




   "t98": {
    "pt": `  `,
    "en": ``,
    "es": ``,
    "fr": ``
  },
   "t99": {
    "pt": `  `,
    "en": ``,
    "es": ``,
    "fr": ``
  },
   "t100": {
    "pt": `  `,
    "en": ``,
    "es": ``,
    "fr": ``
  },
   "t101": {
    "pt": `  `,
    "en": ``,
    "es": ``,
    "fr": ``
  }, 
};

const imageTranslations = {
  "img-1": {
    pt: "https://ik.imagekit.io/fsobpyaa5i/kiosso_image%20(1).png",
    en: "",
    es: "",
    fr: ""
  },
  "img-2": {
    pt: "https://ik.imagekit.io/fsobpyaa5i/Group%2026.png ",
    en: "https://ik.imagekit.io/fsobpyaa5i/img-1-ingles.png",
    es: "https://ik.imagekit.io/fsobpyaa5i/img-1-espanhol.png",
    fr: "https://ik.imagekit.io/fsobpyaa5i/img-1-frances.png"
  },
  "img-3": {
    pt: "https://ik.imagekit.io/fsobpyaa5i/Group%2028.png",
    en: " ",
    es: " ",
    fr: " "
  },
  "img-4": {
    pt: "https://ik.imagekit.io/fsobpyaa5i/Group%2030456.png",
    en: " ",
    es: " ",
    fr: " "
  },
  "img-5": {
    pt: "https://ik.imagekit.io/fsobpyaa5i/Group%2030458%20(1).png",
    en: " ",
    es: " ",
    fr: " "
  },
  "img-6": {
    pt: "https://ik.imagekit.io/fsobpyaa5i/iphone%20copy%2010.png",
    en: " ",
    es: " ",
    fr: " "
  },
  "img-7": {
    pt: "https://ik.imagekit.io/fsobpyaa5i/iphone%20copy%203.png",
    en: " ",
    es: " ",
    fr: " "
  },
  "img-8": {
    pt: "https://ik.imagekit.io/fsobpyaa5i/iphone%20copy%209.png",
    en: " ",
    es: " ",
    fr: " "
  },
  "img-9": {
    pt: "https://ik.imagekit.io/fsobpyaa5i/Group%2030465%20(2).png",
    en: " ",
    es: " ",
    fr: " "
  },
  "img-10": {
    pt: "https://ik.imagekit.io/fsobpyaa5i/Group%2030464.png",
    en: " ",
    es: " ",
    fr: " "
  },
  "img-11": {
    pt: "https://ik.imagekit.io/fsobpyaa5i/Group%2030463%20(1).png",
    en: " ",
    es: " ",
    fr: " "
  },
  "img-12": {
    pt: " ",
    en: " ",
    es: " ",
    fr: " "
  },
  "img-13": {
    pt: " ",
    en: " ",
    es: " ",
    fr: " "
  },
  "img-14": {
    pt: " ",
    en: " ",
    es: " ",
    fr: " "
  },
  "img-15": {
    pt: " ",
    en: " ",
    es: " ",
    fr: " "
  },
};

// --- FUNÇÃO PARA APLICAR TRADUÇÃO DE TEXTOS ---
function applyCustomTranslations(lang) {
  Object.keys(translations).forEach(key => {
    const el = document.getElementById(key);
    if (el && translations[key][lang]) {
      el.innerHTML = translations[key][lang];
    }
  });
}

// --- FUNÇÃO PARA TROCAR IMAGENS ---
function applyImageTranslations(lang) {
  Object.keys(imageTranslations).forEach(key => {  
    const el = document.getElementById(key);
    if (el && imageTranslations[key][lang]) {
      el.src = imageTranslations[key][lang]; 
    }
  });
}

// --- TROCA DE LÍNGUA ---
function setLanguage(lang, code) {
  const combo = document.querySelector('.goog-te-combo');
  if (combo) {
    combo.value = lang;
    combo.dispatchEvent(new Event('change'));
  }

  // Atualiza dropdowns
  document.querySelectorAll('.current-lang').forEach(el => {
    el.textContent = code;
  });

  // Aplica traduções customizadas
  applyCustomTranslations(lang);

  // Aplica troca de imagens
  applyImageTranslations(lang);

  // Salva no localStorage
  localStorage.setItem('selectedLang', lang);
  localStorage.setItem('selectedCode', code);
}

// --- CLIQUE NOS DROPDOWNS ---
document.querySelectorAll('.lang-switcher').forEach(menu => {
  menu.addEventListener('click', (e) => {
    const link = e.target.closest('[data-lang]');
    if (!link) return;
    e.preventDefault();
    setLanguage(link.dataset.lang, link.dataset.code);
  });
});

// --- QUANDO O GOOGLE TRANSLATE TERMINAR ---
const observer = new MutationObserver(() => {
  const combo = document.querySelector('.goog-te-combo');
  if (combo) {
    const savedLang = localStorage.getItem('selectedLang') || 'pt';
    const savedCode = localStorage.getItem('selectedCode') || 'PT';

    // força tradução Google + aplica dicionário
    setLanguage(savedLang, savedCode);

    observer.disconnect();
  }
});
observer.observe(document.documentElement, { childList: true, subtree: true });

// --- LOGO NO REFRESH ---
document.addEventListener("DOMContentLoaded", () => {
  const savedLang = localStorage.getItem('selectedLang') || 'pt';
  const savedCode = localStorage.getItem('selectedCode') || 'PT';

  // atualiza o texto do botão de idioma
  document.querySelectorAll('.current-lang').forEach(el => {
    el.textContent = savedCode;
  });

  setTimeout(() => {
    applyCustomTranslations(savedLang);
    applyImageTranslations(savedLang);
  }, 1000);
});

// --- MENU MOBILE (se existir) ---
let menu = document.querySelector(".mg-menu");
if (menu) {
    let toggle = document.querySelectorAll(".toggle-services-menu");
    toggle.forEach(btn => {
        btn.addEventListener("click", () => {
            console.clear()
            document.querySelectorAll(".mg-menu").forEach(element => {
                element.classList.toggle("show");
            });
        });
    });
}
 
