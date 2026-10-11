# NXDOM

**Nusantara eXtreme Development Object Model**

> Build Open. Develop Extreme. Empower Nusantara.

NXDOM is an open source software organization from Nusantara (Indonesia) — a community of developers building fundamental technology: systems, software architecture, and digital infrastructure that is open, independent, and globally competitive.

This repository contains the official **NXDOM corporate website** — a static, bilingual (Indonesian/English) landing page introducing the organization, its divisions, principles, and project ecosystem.

## Vision

To become an innovative, independent, and globally competitive open source technology developer community from Nusantara.

## Mission

- Develop open source software that can be learned, used, and developed together
- Build strong, flexible, and extensible modular frameworks and systems
- Foster collaboration between programmers, developers, system administrators, and researchers
- Develop open technical standards and documentation
- Build a Nusantara technology ecosystem that contributes to the global community

## Divisions

| Division | Focus |
|---|---|
| **Core** | Core architecture and fundamental object model design |
| **Systems** | Operating systems, server architecture, low-level computing infrastructure |
| **Framework** | Programming frameworks and libraries to accelerate application development |
| **Security** | Application security, code audit, exploit prevention, cryptographic standardization |
| **Community** | Ecosystem collaboration, documentation, and developer education programs |
| **Labs** | Research and experimentation, prototype incubation, and AI development |

## Ecosystem

Open source products born and developed from our community:

- **[NxCode (nxc)](https://nxcodeobject.github.io/coding/)** — AI coding agent right in your terminal. Build & Plan agents, code-aware via LSP, MCP extensions, multi-provider models. ([GitHub](https://github.com/nxcodeobject/code))
- **[NXC Desktop SDK](https://nxcodeobject.github.io/nxc/)** — Native C++ desktop SDK based on Qt 6. Lightweight desktop framework without Electron, with fluent windows, PTY terminal, and runtime bridge.
- **[TeritipDB](https://teritipdb.github.io/website/)** — JSON-based document database engine. Fast, lightweight, and designed for modern application scalability without complicated setup.
- **[NxCode IDE](https://nxcodeobject.github.io/ide/)** — Desktop code editor built on the NXC Desktop SDK. Explorer, search, source control, API client, database, and an AI-agent terminal in a single window, with syntax highlighting for 400+ languages. ([GitHub](https://github.com/nxcodeobject/ide))
- **[NxSocket](https://nxcodeobject.github.io/nxsocket/)** — CLI and WebSocket platform for realtime features: chat, voice/video calls, AI streaming, tracking, and notifications. Every module ready in one token-protected gateway, with flexible data adapters and standalone binaries. ([GitHub](https://github.com/nxcodeobject/nxsocket))

### Ecosystem categories

Every project is labeled by category in the Project Ecosystem section. Visitors can filter the cards using the category chips, or pick a category from the "Ecosystem" dropdown in the navbar (both languages ID/EN):

| Category | Project |
|---|---|
| Agent CLI | NxCode (nxc) |
| Desktop SDK | NXC Desktop SDK |
| IDE Desktop | NxCode IDE |
| Database | TeritipDB |
| Framework CLI | NxDOM CLI |
| Package Manager | NxNode |
| Realtime Platform | NxSocket |

## Open Source Principles

1. **Open Development** — Code, design, and development processes are transparent.
2. **Community Driven** — Contributions, ideas, and discussions from the community are valued.
3. **Modular Architecture** — Every system is built from independent, reusable components.
4. **Transparent Governance** — Technical decisions and project governance are well-documented and fair.
5. **Free & Open Licensing** — We use open licenses such as MIT, Apache-2.0, or GPL depending on the nature of each project.

## Running the website locally

The site is fully static — no build step or dependencies required.

```bash
git clone https://github.com/iyanrsaleh/nxdom.git
cd nxdom
```

Open `index.html` directly in your browser, or serve it locally:

```bash
# any static server works, for example:
python -m http.server 8000
# then visit http://localhost:8000
```

Note: the page loads Google Fonts and Lucide icons from CDNs, so an internet connection is needed for full styling.

## Contributing

Issues and pull requests are welcome. Whether it is a bug fix, translation improvement, or a proposal for the site content — open a discussion on the [issue tracker](https://github.com/iyanrsaleh/nxdom/issues) and let's build together.

## Sponsor

NXDOM and its ecosystem projects are developed in the open and are free to use. If you or your organization want to support our work, consider sponsoring via [GitHub Sponsors](https://github.com/sponsors/iyanrsaleh).

## License

This website is released under the [MIT License](LICENSE).

## Decision log

- Added explicit **category labels** to the Project Ecosystem section plus an
  "Ecosystem" **dropdown in the navbar**, so each project's category is clear.
  Categories are bilingual (ID/EN) and drive an interactive filter: cards carry
  `data-cat`, nav entries carry `data-cat-link`, filtering lives in `index.js`
  (`applyEcoFilter`) and styles in `style.css` (`.eco-filter`, `.eco-chip`,
  `.nav-dropdown`, `.nav-menu`). ✅ DONE
