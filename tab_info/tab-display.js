(() => {
    "use strict";

    const form = document.getElementById("displayConfig");
    const grid = document.getElementById("displayGrid");
    const configPanel = document.querySelector(".config-panel");
    const storageKey = "tab-racing-display-config-v1";
    let refreshTimer = null;

    const meetingMap = new Map(
        TAB_CONFIG.meetingLocations.map(item => [item.id, item])
    );

    function checkedValues(name) {
        return [...form.querySelectorAll(`input[name="${name}[]"]:checked`)]
            .map(input => input.value);
    }

    function readConfig() {
        return {
            displayTypes: checkedValues("displayType"),
            jurisdictions: checkedValues("jurisdiction"),
            meetings: checkedValues("meeting"),
            showHeader: form.showHeader.checked,
            showJurisdiction: form.showJurisdiction.checked,
            showRefresh: form.showRefresh.checked,
            showOpenLink: form.showOpenLink.checked,
            columns: Number(form.columns.value),
            panelHeight: Number(form.panelHeight.value),
            refreshSeconds: Number(form.refreshSeconds.value),
        };
    }

    function writeConfig(config) {
        const groups = {
            displayType: config.displayTypes || [],
            jurisdiction: config.jurisdictions || [],
            meeting: config.meetings || [],
        };

        Object.entries(groups).forEach(([name, values]) => {
            form.querySelectorAll(`input[name="${name}[]"]`).forEach(input => {
                input.checked = values.includes(input.value);
            });
        });

        ["showHeader", "showJurisdiction", "showRefresh", "showOpenLink"].forEach(name => {
            if (typeof config[name] === "boolean") {
                form[name].checked = config[name];
            }
        });

        if (config.columns) form.columns.value = String(config.columns);
        if (config.panelHeight) form.panelHeight.value = String(config.panelHeight);
        if (Number.isFinite(config.refreshSeconds)) {
            form.refreshSeconds.value = String(config.refreshSeconds);
        }
    }

    function buildUrl(displayKey, jurisdiction, page = "") {
        const display = TAB_CONFIG.displayTypes[displayKey];
        if (!display) return null;

        const url = new URL(display.path, TAB_CONFIG.baseUrl);
        url.searchParams.set("channelType", "retail");
        url.searchParams.set("jurisdiction", jurisdiction);

        if (page && display.supports_page) {
            url.searchParams.set("page", page);
        }

        return url.toString();
    }

    function createPanel({ title, subtitle, url, config }) {
        const card = document.createElement("section");
        card.className = "display-card";

        if (config.showHeader) {
            const header = document.createElement("div");
            header.className = "display-card-header";

            const titleWrap = document.createElement("div");
            titleWrap.className = "display-card-title";

            const strong = document.createElement("strong");
            strong.textContent = title;
            titleWrap.appendChild(strong);

            const details = [];
            if (config.showJurisdiction && subtitle) details.push(subtitle);
            if (config.showRefresh) details.push(`Refreshed ${new Date().toLocaleTimeString()}`);

            if (details.length) {
                const small = document.createElement("small");
                small.textContent = details.join(" · ");
                titleWrap.appendChild(small);
            }

            const actions = document.createElement("div");
            actions.className = "display-card-actions";

            const reloadButton = document.createElement("button");
            reloadButton.type = "button";
            reloadButton.className = "mini-button";
            reloadButton.textContent = "Reload";
            reloadButton.addEventListener("click", () => {
                frame.src = frame.src;
            });
            actions.appendChild(reloadButton);

            if (config.showOpenLink) {
                const openButton = document.createElement("button");
                openButton.type = "button";
                openButton.className = "mini-button";
                openButton.textContent = "Open";
                openButton.addEventListener("click", () => {
                    window.open(url, "_blank", "noopener,noreferrer");
                });
                actions.appendChild(openButton);
            }

            header.append(titleWrap, actions);
            card.appendChild(header);
        }

        const frame = document.createElement("iframe");
        frame.className = "display-frame";
        frame.src = url;
        frame.title = title;
        frame.loading = "lazy";
        frame.referrerPolicy = "strict-origin-when-cross-origin";
        frame.allow = "fullscreen";
        card.appendChild(frame);

        return card;
    }

    function createPanels(config) {
        const panels = [];

        config.displayTypes.forEach(displayKey => {
            const display = TAB_CONFIG.displayTypes[displayKey];
            if (!display) return;

            config.jurisdictions.forEach(jurisdiction => {
                const url = buildUrl(displayKey, jurisdiction);
                if (!url) return;

                panels.push({
                    title: display.label,
                    subtitle: TAB_CONFIG.jurisdictions[jurisdiction] || jurisdiction,
                    url,
                });
            });

            if (display.supports_page) {
                config.meetings.forEach(meetingId => {
                    const meeting = meetingMap.get(meetingId);
                    if (!meeting) return;

                    const url = buildUrl(
                        displayKey,
                        meeting.jurisdiction,
                        meeting.page
                    );

                    if (!url) return;

                    panels.push({
                        title: `${display.label}: ${meeting.label}`,
                        subtitle: `${meeting.jurisdiction} · ${meeting.page}`,
                        url,
                    });
                });
            }
        });

        return panels;
    }

    function render(save = true) {
        const config = readConfig();

        if (save) {
            localStorage.setItem(storageKey, JSON.stringify(config));
        }

        grid.replaceChildren();
        grid.style.setProperty("--columns", String(config.columns));
        grid.style.setProperty("--panel-height", `${config.panelHeight}px`);

        const panels = createPanels(config);

        if (!panels.length) {
            const empty = document.createElement("div");
            empty.className = "empty-state";
            empty.innerHTML = "<strong>No display panels selected.</strong><br>Select at least one display item and jurisdiction or meeting location.";
            grid.appendChild(empty);
        } else {
            panels.forEach(panel => {
                grid.appendChild(createPanel({ ...panel, config }));
            });
        }

        document.getElementById("displayTitle").textContent =
            `${panels.length} racing panel${panels.length === 1 ? "" : "s"}`;

        clearInterval(refreshTimer);
        if (config.refreshSeconds > 0) {
            refreshTimer = setInterval(() => refreshFrames(), config.refreshSeconds * 1000);
        }
    }

    function refreshFrames() {
        document.querySelectorAll(".display-frame").forEach(frame => {
            frame.src = frame.src;
        });

        document.querySelectorAll(".display-card-title small").forEach(small => {
            const parts = small.textContent.split(" · ").filter(
                part => !part.startsWith("Refreshed ")
            );
            parts.push(`Refreshed ${new Date().toLocaleTimeString()}`);
            small.textContent = parts.join(" · ");
        });
    }

    form.addEventListener("submit", event => {
        event.preventDefault();
        render(true);

        if (window.innerWidth <= 760) {
            configPanel.classList.add("is-hidden");
        }
    });

    document.querySelectorAll("[data-toggle-group]").forEach(button => {
        button.addEventListener("click", () => {
            const group = button.dataset.toggleGroup;
            const inputs = [...form.querySelectorAll(`input[name="${group}[]"]`)];
            const shouldCheck = inputs.some(input => !input.checked);
            inputs.forEach(input => input.checked = shouldCheck);
        });
    });

    document.getElementById("refreshNow").addEventListener("click", refreshFrames);

    document.getElementById("fullscreenButton").addEventListener("click", async () => {
        try {
            if (!document.fullscreenElement) {
                await document.documentElement.requestFullscreen();
            } else {
                await document.exitFullscreen();
            }
        } catch (error) {
            console.error("Fullscreen unavailable", error);
        }
    });

    document.getElementById("collapseConfig").addEventListener("click", () => {
        configPanel.classList.add("is-hidden");
    });

    document.getElementById("showConfig").addEventListener("click", () => {
        configPanel.classList.remove("is-hidden");
    });

    document.getElementById("resetConfig").addEventListener("click", () => {
        localStorage.removeItem(storageKey);
        location.reload();
    });

    const saved = localStorage.getItem(storageKey);
    if (saved) {
        try {
            writeConfig(JSON.parse(saved));
        } catch (error) {
            console.warn("Saved TAB display configuration was invalid.", error);
        }
    }

    render(false);
})();
