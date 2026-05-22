import { CommandLine } from './bundle.min.js';
import { NexaCommand } from './app.min.js';
import { controllers } from './controllers.js';
export class NexaTerminal {
    constructor(data) {
        this.logs = [];
        this.id = 'NexaTerminal';
        
        this.handleKeyDown = this.handleKeyDown.bind(this);
        // Use capture phase to intercept events early, before other handlers
        document.addEventListener('keydown', this.handleKeyDown, true);
    }
    
    addLog(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        this.logs.push({
            timestamp,
            message,
            type
        });
    }
    
    async domView() {

    setTimeout(async () => {
      try {
        await NXUI.NexaStylesheet.Dom([
            'app.min.css',
        ]);
        const db = new NXUI.NexaDb();
        await db.initDatabase();
        await db.Ref();
        const commandLineElement = document.getElementById('command-linedomView');
        if (!commandLineElement) {
            return;
        }
            const credential = await controllers();
            console.log('credential:', credential);
            const cmd = NexaCommand.instance(commandLineElement, credential,db);

            if (credential?.oauth) {
                cmd.info('Type [help] to show available commands');
                cmd.startNewCommand();
            } else {
                cmd.output('Type [login] to login');
                cmd.run('login');
            }

      } catch (error) {
      }
    }, 100);





      return `<div id="command-linedomView" class="command-line" style="height:340px"></div>`
    }
    async open() {
        if (typeof NXUI === 'undefined') {
            return;
        }

        await NXUI.NexaStylesheet.Dom([
            'app.min.css',
        ]);

        const db = new NXUI.NexaDb();
        await db.initDatabase();
        await db.Ref();

        const modalID = "open_" + this.id;
        NXUI.modalHTML({
            elementById: modalID,
            styleClass: "w-700px",
            minimize: true,
            label: `Terminal`,
            setDataBy: false,
            onclick: false,
            content: `<div id="command-line" class="command-line"></div>`,
        });
        NXUI.nexaModal.open(modalID);
        NXUI.id("body_"+modalID).setStyle("padding", "0px")
        // Wait for DOM to be ready
        // await new Promise(resolve => setTimeout(resolve, 200));
        
        const commandLineElement = document.getElementById('command-line');
        if (!commandLineElement) {
            return;
        }
  // const cmd2 = DemoCommand.instance(document.getElementById('command-line-2'));
// cmd2.output('Type any username to login');
// cmd2.run('login');
            const credential = await controllers();
            const cmd = NexaCommand.instance(commandLineElement, credential, db);

            if (credential?.oauth) {
                cmd.info('Type [help] to show available commands');
                cmd.startNewCommand();
            } else {
                cmd.output('Type [login] to login');
                cmd.run('login');
            }
        



    }
    
    close() {
        if (typeof NXUI === 'undefined') {
            return;
        }
        
        const modalID = "open_" + this.id;
        NXUI.nexaModal.close(modalID);
    }
    
    showHelp() {
    }
    
    clearLogs() {
        this.logs.length = 0;
    }
    
    handleKeyDown(e) {
        // Check for Ctrl+Z, Ctrl+Shift+Z, or Ctrl+Shift+X
        const isZ = e.keyCode === 90 || e.key === 'z' || e.key === 'Z';
        const isX = e.keyCode === 88 || e.key === 'x' || e.key === 'X';
        
        // Must have Ctrl pressed
        if (!e.ctrlKey) {
            return;
        }
        
        // Don't handle if user is typing in input/textarea (unless terminal is open)
        const terminalOpen = document.getElementById('command-line') !== null;
        if (!terminalOpen && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable)) {
            return;
        }
        
        // Ctrl+Z (without Shift) - Open terminal
        if (isZ && !e.shiftKey) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            this.open();
        }
        // Ctrl+Shift+Z - Open terminal
        else if (isZ && e.shiftKey) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            this.open();
        }
        // Ctrl+Shift+X - Close terminal
        else if (isX && e.shiftKey) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            this.close();
        }
    }
    
    get shortcuts() {
        return {
            'Ctrl+Z': 'Open',
            'Ctrl+Shift+Z': 'Open',
            'Ctrl+Shift+X': 'Close'
        };
    }
}
