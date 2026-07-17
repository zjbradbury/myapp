(() => {
    "use strict";

    const form = document.getElementById("displayConfig");
    const displayGrid = document.getElementById("displayGrid");
    const configPanel = document.getElementById("configPanel");
    const storageKey = "tab-sa-racing-display-v3";

    function normaliseCodes(value) {
        return String(value || "")
            .toUpperCase()
            .split(/[\s,\-]+/)
            .map(code => code.trim())
            .filter(code => /^[A-Z0-9]{2,4}$/.test(code));
    }

    function selectedCheckboxCodes() {
        return [...form.querySelectorAll('input[name="meetingCode[]"]:checked')]
            .map(input => input.value.toUpperCase());
    }

    function selectedCodes() {
        return [...new Set([
            ...selectedCheckboxCodes(),
            ...normaliseCodes(form.customCodes.value),
        ])];
    }

    function readConfig() {
        return {
            showNextToJump: form.showNextToJump.checked,
            showGallery: form.showGallery.checked,
            meetingCodes: selectedCheckboxCodes(),
            customCodes: form.customCodes.value,
            panelHeight: Number(form.panelHeight.value),
            showPanelHeading: form.showPanelHeading.checked,
        };
    }

    function writeConfig(config) {
        if (typeof config.showNextToJump === "boolean") {
            form.showNextToJump.checked = config.showNextToJump;
        }
        if (typeof config.showGallery === "boolean") {
            form.showGallery.checked = config.showGallery;
        }
        if (typeof config.showPanelHeading === "boolean") {
            form.showPanelHeading.checked = config.showPanelHeading;
        }

        const savedCodes = Array.isArray(config.meetingCodes) ? config.meetingCodes : [];
        form.querySelectorAll('input[name="meetingCode[]"]').forEach(input => {
            input.checked = savedCodes.includes(input.value);
        });

        form.customCodes.value = config.customCodes || "";

        if (config.panelHeight) {
            form.panelHeight.value = String(config.panelHeight);
        }
    }

    function buildUrl(baseUrl, pageCodes = []) {
        const url = new URL(baseUrl);
        url.searchParams.set("jurisdiction", TAB_CONFIG.jurisdiction);
        url.searchParams.set("channelType", TAB_CONFIG.channelType);

        if (pageCodes.length) {
            url.searchParams.set("page", pageCodes.join("-"));
        }

        return url.toString();
    }

    function updatePreview() {
        const codes = selectedCodes();
        document.getElementById("pageCodePreview").textContent =
            codes.length ? codes.join("-") : "No codes selected";
    }

    function enterPanelFullscreen(card) {
        if (card.requestFullscreen) {
            card.requestFullscreen();
        } else if (card.webkitRequestFullscreen) {
            card.webkitRequestFullscreen();
        }
    }

    function createDisplayCard(title, url, config, subtitle) {
        const card = document.createElement("section");
        card.className = "display-card";
        card.style.setProperty("--panel-height", `${config.panelHeight}px`);

        if (config.showPanelHeading) {
            const header = document.createElement("div");
            header.className = "display-card-header";

            const headingWrap = document.createElement("div");
            headingWrap.className = "card-heading";

            const heading = document.createElement("strong");
            heading.textContent = title;

            const subheading = document.createElement("small");
            subheading.textContent = subtitle || "SA · retail";

            headingWrap.append(heading, subheading);

            const actions = document.createElement("div");
            actions.className = "card-actions";

            const fullscreenButton = document.createElement("button");
            fullscreenButton.type = "button";
            fullscreenButton.className = "mini-button";
            fullscreenButton.textContent = "Fullscreen";
            fullscreenButton.title = "Fullscreen this TAB panel only";
            fullscreenButton.addEventListener("click", () => enterPanelFullscreen(card));

            actions.appendChild(fullscreenButton);
            header.append(headingWrap, actions);
            card.appendChild(header);
        }

        const frameWrap = document.createElement("div");
        frameWrap.className = "frame-wrap";

        const frame = document.createElement("iframe");
        frame.className = "tab-frame";
        frame.src = url;
        frame.title = title;
        frame.allow = "fullscreen";
        frame.loading = "eager";
        frame.referrerPolicy = "strict-origin-when-cross-origin";

        frameWrap.appendChild(frame);
        card.appendChild(frameWrap);

        if (!config.showPanelHeading) {
            const floatingFullscreen = document.createElement("button");
            floatingFullscreen.type = "button";
            floatingFullscreen.className = "floating-fullscreen";
            floatingFullscreen.textContent = "Fullscreen";
            floatingFullscreen.addEventListener("click", () => enterPanelFullscreen(card));
            card.appendChild(floatingFullscreen);
        }

        return card;
    }

    function render(save = true) {
        const config = readConfig();
        const codes = selectedCodes();

        if (save) {
            localStorage.setItem(storageKey, JSON.stringify(config));
        }

        displayGrid.replaceChildren();
        let panelCount = 0;

        if (config.showNextToJump) {
            if (codes.length) {
                const url = buildUrl(TAB_CONFIG.racingDetailUrl, codes);
                displayGrid.appendChild(
                    createDisplayCard(
                        `Next to jump — ${codes.join("-")}`,
                        url,
                        config,
                        `SA · retail · page=${codes.join("-")}`
                    )
                );
                panelCount++;
            } else {
                const error = document.createElement("div");
                error.className = "empty-state";
                error.textContent = "Select at least one meeting code for Next to jump.";
                displayGrid.appendChild(error);
            }
        }

        if (config.showGallery) {
            const url = buildUrl(TAB_CONFIG.galleryUrl);
            displayGrid.appendChild(
                createDisplayCard(
                    "Gallery with triple results",
                    url,
                    config,
                    "SA · retail · fixed gallery"
                )
            );
            panelCount++;
        }

        if (!config.showNextToJump && !config.showGallery) {
            const empty = document.createElement("div");
            empty.className = "empty-state";
            empty.textContent = "Select at least one TAB display.";
            displayGrid.appendChild(empty);
        }

        document.getElementById("displayTitle").textContent =
            `${panelCount} TAB panel${panelCount === 1 ? "" : "s"}`;

        updatePreview();
    }

    form.addEventListener("submit", event => {
        event.preventDefault();
        render(true);

        if (window.innerWidth <= 800) {
            configPanel.classList.add("is-hidden");
        }
    });

    form.addEventListener("input", updatePreview);
    form.addEventListener("change", updatePreview);

    document.querySelectorAll("[data-codes]").forEach(button => {
        button.addEventListener("click", () => {
            const codes = button.dataset.codes.split(",");
            form.querySelectorAll('input[name="meetingCode[]"]').forEach(input => {
                input.checked = codes.includes(input.value);
            });
            form.customCodes.value = "";
            updatePreview();
        });
    });

    document.getElementById("clearCodes").addEventListener("click", () => {
        form.querySelectorAll('input[name="meetingCode[]"]').forEach(input => {
            input.checked = false;
        });
        form.customCodes.value = "";
        updatePreview();
    });

    document.getElementById("hideConfig").addEventListener("click", () => {
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
            console.warn("Could not load saved TAB display configuration.", error);
        }
    }

    updatePreview();
    render(false);
})();
