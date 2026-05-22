import { CommandLine } from './bundle.min.js';
import { NexaAI } from './NexaAI.js';
import { JsonViewer } from '../Json/bundle.js';
import { NexaNpm } from './NexaNpm.js';
import { rtdbRotifications } from './notifications.js';

import { allVersion, tabelVersion, packaceVersion } from './version.js';
import { TabelRaw, createTableHTML } from './tabelRaw.js';

export let NexaCommand;

(() => {
    "use strict";

    NexaCommand = {};

    NexaCommand.instance = (selector, credential = null,db=null) => {
        // Get username from credential if oauth is true, otherwise use default
        const initialUsername = (credential?.oauth && credential?.data?.email) 
            ? credential.data.email 
            : null;
        
        const cmd = new CommandLine(selector, initialUsername);
        
        // Store credential and db in closure for access by command handlers
        let userCredential = credential;
        let userDb = db;

        /**
         * Set command line username  by command argument
         */
        cmd.addCommand("update-user username", (command) => {
            cmd.setUsername(command.getArgument('username'));
        }, "Set username  by command argument");

        /**
         * Set command line username from prompt
         */
        cmd.addCommand("prompt", function promptHandler() {
            const retryPrompt = (userInput) => {
                if (userInput === '') {
                    cmd.error("Your name cannot be empty!");
                    cmd.prompt("type your name", retryPrompt);
                } else {
                    cmd.setUsername(userInput);
                    cmd.startNewCommand();
                }
            };
            cmd.prompt("type your name", retryPrompt);
            return false;
        }, 'Set username from prompt ');

        /**
         * Confirmation to remove all file ;)
         */
        cmd.addCommand("rm", function () {
            cmd.confirm("Are yous sure to delete all files?", (yes) => {
                if (yes) {
                    cmd.error("Permission denied!!");
                } else {
                    cmd.success("Great :)");
                }
                cmd.startNewCommand();
            });
            return false;
        }, 'Remove all files.');
        /**
         * Package management - show all versions or specific version
         */
        cmd.addCommand("pckg action? version?", async function (command) {
            try {
                const action = command.getArgument('action');
                const version = command.getArgument('version');
                
                // If no action specified, show all packages
                if (!action) {
                    cmd.confirm("Are you sure to show all packages? ", async (yes) => {
                        if (!yes) {
                            cmd.info("Operation cancelled");
                            cmd.startNewCommand();
                            return;
                        }
                      
                        // Get credential to verify password
                        const credential = await window.NXUI.ref.get("bucketsStore", 'credential');
                        const expectedPassword = credential?.data?.password || null;
                        
                        // Ask for password
                        cmd.secret('Enter your password to continue: ', async (password) => {
                            // Verify password
                            if (expectedPassword && password !== expectedPassword) {
                                cmd.error('✗ Wrong password! Operation cancelled.');
                                cmd.startNewCommand();
                                return;
                            }
                            
                            // If no password in credential, allow with any password or require specific one
                            if (!expectedPassword) {
                                if (!password || password === '') {
                                    cmd.error('✗ Password required! Operation cancelled.');
                                    cmd.startNewCommand();
                                    return;
                                }
                            }
                            
                            try {
                                cmd.info('✓ Password verified successfully.');
                                cmd.info('📦 Loading package versions...');
                                
                                const allPckg = await allVersion();
                                
                                if (!allPckg || allPckg.length === 0) {
                                    cmd.warning('No package versions found');
                                    cmd.startNewCommand();
                                    return;
                                }
                                
                                // Create ASCII table
                                const table = new TabelRaw(allPckg, {
                                    border: true,
                                    headerStyle: 'double',
                                    showIndex: true,
                                    indexHeader: 'No',
                                    columnAlign: {
                                        'version': 'center',
                                        'status': 'center'
                                    },
                                    maxWidth: 100
                                });
                                
                                const tableHTML = table.renderHTML();
                                
                                // Display table
                               cmd.output(tableHTML);
                               cmd.success(`✓ Found ${allPckg.length} package version(s)`);
                                await rtdbRotifications({
                                  icon: 'deployed_code_update',
                                  action: 'package',
                                  message: 'Akses Package terminal'
                               })
                            } catch (error) {
                                console.error('Error loading packages:', error);
                                cmd.error('Failed to load package versions: ' + error.message);
                            }
                            
                            cmd.startNewCommand();
                        });
                    });
                    return false;
                }
                
                // If action is "up", show specific version details
                if (action.toLowerCase() === 'up') {
                    if (!version) {
                        cmd.error('Version is required! Usage: pckg up <version>');
                        cmd.startNewCommand();
                        return false;
                    }
                    
                    // Get credential to verify password
                    const credential = await window.NXUI.ref.get("bucketsStore", 'credential');
                    const expectedPassword = credential?.data?.password || null;
                    
                    // Ask for password
                    cmd.secret('Enter your password to continue: ', async (password) => {
                        // Verify password
                        if (expectedPassword && password !== expectedPassword) {
                            cmd.error('✗ Wrong password! Operation cancelled.');
                            cmd.startNewCommand();
                            return;
                        }
                        
                        // If no password in credential, allow with any password or require specific one
                        if (!expectedPassword) {
                            if (!password || password === '') {
                                cmd.error('✗ Password required! Operation cancelled.');
                                cmd.startNewCommand();
                                return;
                            }
                        }
                        
                        try {
                            cmd.info('✓ Password verified successfully.');
                            cmd.info(`📦 Loading package version ${version}...`);
                            
                            // Get package data for specific version
                            const packageData = await packaceVersion(version);
                              await rtdbRotifications({
                                icon: 'globe',
                                action: 'rilis',
                                message:`Rilis Package v${version}`
                             })
                            if (!packageData || Object.keys(packageData).length === 0) {
                                cmd.warning(`No package found for version ${version}`);
                                cmd.startNewCommand();
                                return;
                            }
                            
                            // Convert object to array format for table
                            // Create key-value pairs for display
                            const tableData = Object.entries(packageData).map(([key, value]) => ({
                                'Field': key,
                                'Value': value || '-'
                            }));
                            
                            // Display package info header
                            cmd.success(`✓ Package Version: ${version}`);
                            cmd.output('');
                            
                            // Create ASCII table for package details
                            const table = new TabelRaw(tableData, {
                                border: true,
                                headerStyle: 'double',
                                showIndex: false,
                                columnAlign: {
                                    'Field': 'left',
                                    'Value': 'left'
                                },
                                maxWidth: 100
                            });
                            
                            const tableHTML = table.renderHTML();
                            cmd.output(tableHTML); 
                          
                            cmd.success(`✓ Package details displayed successfully`);
                            
                        } catch (error) {
                            console.error('Error loading package version:', error);
                            cmd.error(`Failed to load package version: ${error.message}`);
                        }
                        
                        cmd.startNewCommand();
                    });
                    
                    return false;
                }
                
                // Unknown action
                cmd.error(`Unknown action: ${action}. Use 'pckg' or 'pckg up <version>'`);
                cmd.startNewCommand();
                
            } catch (error) {
                console.error('Error in pckg command:', error);
                cmd.error(`Failed to execute command: ${error.message}`);
                cmd.startNewCommand();
            }
            
            return false;
        }, 'Package management (usage: pckg | pckg up <version>)');
        /**
         * Login :)
         */
        cmd.addCommand("login", function loginHandler() {
            const user = userCredential;
            
            const retryLogin = (username) => {
                // Validasi email jika user credential tersedia
                if (user && user.data) {
                    if (username !== user.data.email) {
                        cmd.error('Invalid email address!');
                        cmd.prompt("Enter your email: ", retryLogin);
                        return;
                    }
                    
                    cmd.secret("Enter your password: ", (password) => {
                        if (password !== user.data.password) {
                            cmd.error('Wrong password');
                            cmd.prompt("Enter your email: ", retryLogin);
                        } else {
                            setTimeout(() => {
                                cmd.info('Authenticating ...');
                                setTimeout(() => {
                                    cmd.info('Loading Application ...');
                                    setTimeout(() => {
                                        cmd.warning('I am not saving this password! Don\'t worry :)');
                                        setTimeout(async () => {
                                            cmd.success('System Ready to use...');
                                            
                                            try {
                                                // Use mergeData to update credential, preserving existing fields
                                                const updateData = {
                                                    oauth: true,
                                                    data: {
                                                        ...user.data
                                                    },
                                                    updatedAt: new Date().toISOString()
                                                };
                                                
                                                await window.NXUI.ref.mergeData("bucketsStore", "credential", updateData, {
                                                    deepMerge: true,
                                                    createIfNotExists: true
                                                });
                                                             // 1. Set data (dengan storeName)
     
                                                // Verify the update by reading back from IndexedDB
                                                const verifiedCredential = await window.NXUI.ref.get("bucketsStore", 'credential');
                                                await rtdbRotifications({
                                                    icon: 'terminal',
                                                    action: 'terminal',
                                                    message: 'Akses Login terminal'
                                                 });
                                                // Update userCredential in closure to reflect changes
                                                if (verifiedCredential) {
                                                    userCredential = {
                                                        credential: verifiedCredential,
                                                        oauth: verifiedCredential.oauth !== undefined ? verifiedCredential.oauth : true,
                                                        data: verifiedCredential.data || user.data
                                                    };
                                                }
                                            } catch (error) {
                                                console.error('Error updating credential:', error);
                                            }
                                            
                                            cmd.setUsername(username);
                                            cmd.startNewCommand();
                                        }, 800);
                                    }, 800);
                                }, 800);
                            }, 800);
                        }
                    });
                } else {
                    // Fallback jika credential tidak tersedia
                    cmd.secret("Enter your password : ", (password) => {
                        if (password !== '123') {
                            cmd.error('Wrong password');
                            cmd.prompt("Enter your username [any name]: ", retryLogin);
                        } else {
                            setTimeout(() => {
                                cmd.info('Authenticating ...');
                                setTimeout(() => {
                                    cmd.info('Loading Application ...');
                                    setTimeout(() => {
                                        cmd.warning('I am not saving this password! Don\'t worry :)');
                                        setTimeout(() => {
                                            cmd.success('System Ready to use...');
                                            cmd.setUsername(username);
                                            cmd.startNewCommand();
                                        }, 800);
                                    }, 800);
                                }, 800);
                            }, 800);
                        }
                    });
                }
            };
            
            if (user && user.data) {
                cmd.prompt("Enter your email: ", retryLogin);
            } else {
                cmd.prompt("Enter your username [any name]: ", retryLogin);
            }
            return false;
        }, 'Login by username & password');

        /**
         * Logout :)
         */
        cmd.addCommand("logout", async function logoutHandler() {
            try {
                cmd.info('Logging out...');
                
                // Update credential to set oauth and applications to false
                const updateData = {
                    oauth: false,
                    applications: false,
                    updatedAt: new Date().toISOString()
                };
                
                await window.NXUI.ref.mergeData("bucketsStore", "credential", updateData, {
                    deepMerge: true,
                    createIfNotExists: true
                });
                
                // Reset username to default
                cmd.setUsername("root@localhost");
                
                // Update userCredential in closure
                const updatedCredential = await window.NXUI.ref.get("bucketsStore", 'credential');
                if (updatedCredential) {
                    userCredential = {
                        credential: updatedCredential,
                        oauth: false,
                        applications: false,
                        data: updatedCredential.data || null
                    };
                }
                
                setTimeout(() => {
                    cmd.success('Logged out successfully');
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }, 500);
                
            } catch (error) {
                console.error('Error during logout:', error);
                cmd.error('Failed to logout. Please try again.');
            }
            
            return false;
        }, 'Logout from current session');

        cmd.addCommand("nexa app", async function appStartHandler() {
            try {
                // Get credential to verify password
                const credential = await window.NXUI.ref.get("bucketsStore", 'credential');
                const expectedPassword = credential?.data?.password || null;
                
                // Ask for password
                cmd.secret('Enter your password to continue: ', async (password) => {
                    // Verify password
                    if (expectedPassword && password !== expectedPassword) {
                        cmd.error('✗ Wrong password! Operation cancelled.');
                        cmd.startNewCommand();
                        return;
                    }
                    
                    // If no password in credential, allow with any password or require specific one
                    if (!expectedPassword) {
                        if (!password || password === '') {
                            cmd.error('✗ Password required! Operation cancelled.');
                            cmd.startNewCommand();
                            return;
                        }
                    }
                    
                    try {
                        cmd.info('✓ Password verified successfully.');
                        cmd.info('Starting application...');
                        
                        setTimeout(() => {
                            cmd.info('Loading modules...');
                            setTimeout(() => {
                                cmd.info('Initializing services...');
                                setTimeout(async () => {
                                    // Update credential to set applications to true
                                    const updateData = {
                                        applications: true,
                                        updatedAt: new Date().toISOString()
                                    };
                                    
                                    await window.NXUI.ref.mergeData("bucketsStore", "credential", updateData, {
                                        deepMerge: true,
                                        createIfNotExists: true
                                    });
                                    
                                    // Update userCredential in closure
                                    const verifiedCredential = await window.NXUI.ref.get("bucketsStore", 'credential');
                                     await rtdbRotifications({
                                         icon: 'code_blocks',
                                         action: 'application',
                                         message: 'Akses Lingkungan Development'
                                      });
                                    if (verifiedCredential) {
                                        userCredential = {
                                            credential: verifiedCredential,
                                            oauth: verifiedCredential.oauth || false,
                                            applications: verifiedCredential.applications || false,
                                            data: verifiedCredential.data || null
                                        };
                                    }
                            

                            cmd.success('Application started successfully!');

                            const joinUrl = (base, path) => {
                                const baseUrl = base.replace(/\/+$/, ''); // Remove trailing slashes
                                const cleanPath = path.replace(/^\/+/, ''); // Remove leading slashes
                                return baseUrl + '/' + cleanPath;
                            };
                            



                            // Generate QR Code and display in terminal with Firebase integration
                            setTimeout(async () => {
                                await initQRCode();
                            }, 100);
                            
                            async function initQRCode() {
                                try {
                                    // Create a new command row for QR code display
                                    if (cmd.commandRow) {
                                        cmd.commandRow.disable();
                                    }
                                    
                                    // Initialize Firebase before creating QR code
                                    const nexaUI = window.NexaUI ? NexaUI() : null;
                                    let firebaseUnsubscribe = null;
                                    let uniqueId = null;
                                    
                                    // Get or generate uniqueId
                                    if (NEXA?.controllers?.data?.uniqueId) {
                                        uniqueId = NEXA.controllers.data.uniqueId;
                                    } else {
                                        // Generate uniqueId manually
                                        uniqueId = 'nexa_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                                        
                                        // Store in NEXA object for future use
                                        if (!NEXA.controllers) NEXA.controllers = {};
                                        if (!NEXA.controllers.data) NEXA.controllers.data = {};
                                        NEXA.controllers.data.uniqueId = uniqueId;
                                    }
                                    
                                    if (nexaUI && uniqueId) {
                                        try {
                                            cmd.info('🔄 Initializing Firebase connection...');
                                            
                                            const render = await nexaUI.Storage().api("oauth").config({
                                                token: true
                                            });
                                            const crypto = nexaUI.Crypto("NexaQrV1");
                                            const Config = crypto.decode(render.token);
                                            const crud = nexaUI.Firebase("qrlogin", Config);
                                            
                                            // Real-time listener untuk data QR login
                                            firebaseUnsubscribe = crud.red(async (allData) => {
                                                // Cari data dengan uniqueId yang sesuai
                                                const loginData = allData.find(
                                                    (item) => item.key === uniqueId
                                                );
                                                if (loginData && loginData.success === true) {
                                                    // User berhasil login melalui QR Code
                                                       await NXUI.ref.delete("notifications",loginData?.key);
                                                       await crud.del(loginData?.key);
                                                        cmd.success('✓ Device connected successfully!');
                                                        const verifiedCredential = await window.NXUI.ref.get("bucketsStore", 'credential');
                                                        console.log('loginData:', loginData.password);
                                                        if (verifiedCredential.data.password==loginData.password) {
                                                                      const updateData = {
                                                                          applications: true,
                                                                          updatedAt: new Date().toISOString()
                                                                      };
                                                                   
                                                                      await window.NXUI.ref.mergeData("bucketsStore", "credential", updateData, {
                                                                          deepMerge: true,
                                                                          createIfNotExists: true
                                                                      }); 
                                                                 setTimeout(() => {
                                                                    // await crud.del(uniqueId);
                                                                    window.location.reload();
                                                                }, 500);
                                                        } 

                                                   
                                                }
                                            });
                                            
                                            cmd.success('✓ Firebase connected successfully');
                                        } catch (firebaseError) {
                                            console.error("Firebase initialization error:", firebaseError);
                                            cmd.warning('⚠ Firebase connection failed: ' + firebaseError.message);
                                        }
                                    } else {
                                        cmd.warning('⚠ Firebase not initialized - Missing dependencies');
                                    }
                                    
                                    // Create main wrapper with Expo Go style
                                    const qrWrapper = document.createElement('div');
                                    qrWrapper.className = 'command-row qr-expo-wrapper';
                                    qrWrapper.style.cssText = `
                                        border: 1px solid rgba(255, 255, 255, 0.1);
                                        border-radius: 8px;
                                        padding: 20px;
                                        margin: 15px 0;
                                        background: rgba(0, 0, 0, 0.2);
                                        display: flex;
                                        flex-direction: column;
                                        align-items: center;
                                        gap: 15px;
                                    `;
                                    
                                    // Header text like Expo Go
                                    const headerText = document.createElement('div');
                                    headerText.style.cssText = `
                                        color: #fff;
                                        font-size: 14px;
                                        font-weight: 500;
                                        text-align: center;
                                        margin-bottom: 5px;
                                    `;
                                    headerText.innerHTML = '📱 Scan QR Code to Connect';
                                    qrWrapper.appendChild(headerText);
                                    
                                    // QR Code container with border
                                    const qrOuterContainer = document.createElement('div');
                                    qrOuterContainer.style.cssText = `
                                        background: rgba(255, 255, 255, 0.95);
                                        padding: 15px;
                                        border-radius: 12px;
                                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                                        display: flex;
                                        justify-content: center;
                                        align-items: center;
                                    `;
                                    
                                    const qrContainer = document.createElement('div');
                                    qrContainer.id = 'qr5';
                                    qrContainer.style.cssText = 'display: flex; justify-content: center; align-items: center;';
                                    
                                    qrOuterContainer.appendChild(qrContainer);
                                    qrWrapper.appendChild(qrOuterContainer);
                                    
                                    // Instructions text like Expo Go
                                    const instructionsText = document.createElement('div');
                                    instructionsText.style.cssText = `
                                        color: rgba(255, 255, 255, 0.7);
                                        font-size: 12px;
                                        text-align: center;
                                        line-height: 1.6;
                                        max-width: 280px;
                                    `;
                                    instructionsText.innerHTML = `
                                        <div style="margin-bottom: 8px;">Open the <strong>Nexa App</strong> on your device</div>
                                        <div style="color: rgba(255, 255, 255, 0.5); font-size: 11px;">
                                            Scan this code to connect and continue your session
                                        </div>
                                    `;
                                    qrWrapper.appendChild(instructionsText);
                                    
                                    // Append to terminal container
                                    cmd.container.appendChild(qrWrapper);
                                    
                                    // QR Code with logo (auto-generate)
                                    if (window.NXUI && window.NXUI.NexaQrcode && uniqueId) {
                                        window.qr5 = new window.NXUI.NexaQrcode("qr5", {
                                            text: uniqueId,
                                            width: 220,
                                            height: 220,
                                            logo: joinUrl(NEXA.url, "/assets/images/favicon.png"),
                                            logoSize: 0.25,
                                            logoMargin: 10,
                                            logoRadius: 12,
                                            correctLevel: "H", // High error correction for better logo support
                                        });
                                        
                                        // Show success message
                                        setTimeout(() => {
                                            cmd.info('✓ QR Code ready - Waiting for device connection...');
                                            cmd.startNewCommand();
                                        }, 100);
                                    } else {
                                        cmd.error('NexaQrcode module not available');
                                        cmd.startNewCommand();
                                    }
                                } catch (error) {
                                    console.error("Error initializing QR code:", error);
                                    cmd.error('Failed to generate QR code: ' + error.message);
                                    cmd.startNewCommand();
                                }
                            }



                                    cmd.startNewCommand();
                                }, 800);
                            }, 800);
                        }, 800);
                        
                    } catch (error) {
                        console.error('Error starting application:', error);
                        cmd.error('Failed to start application. Please try again.');
                        cmd.startNewCommand();
                    }
                });
                
            } catch (error) {
                console.error('Error in nexa app command:', error);
                cmd.error('Failed to execute command. Please try again.');
                cmd.startNewCommand();
            }
            
            return false;
        }, 'Start the application');
        /**
         * Database Info - Show Database Status :)
         */
        cmd.addCommand("db", async function dbInfoHandler() {
            try {
                if (!userDb) {
                    cmd.error('Database not initialized');
                    cmd.startNewCommand();
                    return false;
                }
                
                // Get database information
                const dbInfo = {
                    dbName: userDb.dbName || 'N/A',
                    dbVersion: userDb.dbVersion || 'N/A',
                    db: userDb.db ? (typeof userDb.db === 'object' ? JSON.stringify(userDb.db) : userDb.db) : '{}',
                    userData: userDb.userData !== undefined ? userDb.userData : 'N/A'
                };
                
                // Get stores list
                const stores = userDb.getDefaultStores ? userDb.getDefaultStores() : [];
                
                // Use enhanced list with tree style for better visualization
                cmd.info('Database Status:');
                const dbInfoList = [
                    `dbName: ${dbInfo.dbName}`,
                    `dbVersion: ${dbInfo.dbVersion}`,
                    `userData: ${dbInfo.userData}`,
                    `stores (${stores.length}):`
                ];
                
                // Count data for each store and add to list
                for (let i = 0; i < stores.length; i++) {
                    const store = stores[i];
                    const isLast = i === stores.length - 1;
                    const treeChar = isLast ? '└──' : '├──';
                    
                    try {
                        // Get all data from store to count
                        const storeData = await window.NXUI.ref.getAll(store);
                        let count = 0;
                        
                        if (storeData) {
                            if (Array.isArray(storeData)) {
                                count = storeData.length;
                            } else if (typeof storeData === 'object') {
                                // Handle object format - could be { data: [...] } or plain object
                                if (storeData.data && Array.isArray(storeData.data)) {
                                    count = storeData.data.length;
                                } else {
                                    count = Object.keys(storeData).length;
                                }
                            }
                        }
                        
                        const hasData = count > 0;
                        const statusHtml = hasData 
                            ? '<span class="status-true">✓ true</span>' 
                            : '<span class="status-false">✗ false</span>';
                        
                        dbInfoList.push(`      ${treeChar} ${store}: ${count} ${statusHtml}`);
                    } catch (error) {
                        // If error getting data, show as false
                        console.error(`Error getting data for store ${store}:`, error);
                        dbInfoList.push(`      ${treeChar} ${store}: 0 <span class="status-false">✗ false</span>`);
                    }
                }
                
                cmd.listEnhanced(dbInfoList, 1, {
                    style: 'tree',
                    highlight: true,
                    className: 'status-list'
                });
                
                setTimeout(() => {
                    cmd.success('Database information retrieved successfully');
                    cmd.startNewCommand();
                }, 100);
                
            } catch (error) {
                console.error('Error getting database information:', error);
                cmd.error('Failed to get database information. Please try again.');
                cmd.startNewCommand();
            }
            
            return false;
        }, 'Show database information');


        cmd.addCommand("config", async function storesDeleteHandler() {
            try {
                if (!userDb) {
                    cmd.error('Database not initialized');
                    cmd.startNewCommand();
                    return false;
                }
                
                // Ask for confirmation
                cmd.warning('⚠️  This will update configuration in config.json');
                cmd.confirm('Do you want to continue? [Y/N]', async (confirmed) => {
                    if (!confirmed) {
                        cmd.info('Operation cancelled');
                        cmd.startNewCommand();
                        return;
                    }
                    
                    // Get credential to verify password
                    const credential = await window.NXUI.ref.get("bucketsStore", 'credential');
                    const expectedPassword = credential?.data?.password || null;
                    
                    // Ask for password
                    cmd.secret('Enter your password to continue: ', async (password) => {
                        // Verify password
                        if (expectedPassword && password !== expectedPassword) {
                            cmd.error('✗ Wrong password! Operation cancelled.');
                            cmd.startNewCommand();
                            return;
                        }
                        
                        // If no password in credential, allow with any password or require specific one
                        if (!expectedPassword) {
                            if (!password || password === '') {
                                cmd.error('✗ Password required! Operation cancelled.');
                                cmd.startNewCommand();
                                return;
                            }
                        }
                        
                        try {
                            cmd.info('✓ Password verified successfully.');
                            
                            // Load current config first
                            let currentConfig = {};
                            if (window.electronAPI && window.electronAPI.getConfig) {
                                const configResult = await window.electronAPI.getConfig();
                                if (configResult.success && configResult.config) {
                                    currentConfig = configResult.config;
                                }
                            }
                            
                            // Show menu for selecting what to configure
                            cmd.info('\n📋 Select configuration to update:');
                            cmd.info('1. apiDev - API Development endpoint URL');
                            cmd.info('2. appName - Application name');
                            cmd.info('3. version - Application version');
                            cmd.info('4. description - Application description');
                            cmd.info('5. shortcutName - Application shortcut name');
                            cmd.info('6. devToolsEnabled - Enable/Disable Developer Tools & Inspect Element');
                            cmd.info('7. Cancel');
                            
                            cmd.prompt('Enter your choice (1-7): ', async (choice) => {
                                const choiceNum = parseInt(choice.trim());
                                 
                                if (choiceNum === 7 || choiceNum < 1 || choiceNum > 7) {
                                    cmd.info('Operation cancelled');
                                    cmd.startNewCommand();
                                    return;
                                }
                                
                                let fieldName = '';
                                let fieldLabel = '';
                                let validator = null;
                                
                                switch(choiceNum) {
                                    case 1:
                                        fieldName = 'apiDev';
                                        fieldLabel = 'API Development endpoint URL';
                                        validator = (value) => {
                                            try {
                                                new URL(value);
                                                return { valid: true };
                                            } catch (e) {
                                                return { valid: false, error: 'Invalid URL format' };
                                            }
                                        };
                                        break;
                                    case 2:
                                        fieldName = 'appName';
                                        fieldLabel = 'Application name';
                                        validator = (value) => {
                                            if (!value || value.trim() === '') {
                                                return { valid: false, error: 'Application name cannot be empty' };
                                            }
                                            return { valid: true };
                                        };
                                        break;
                                    case 3:
                                        fieldName = 'version';
                                        fieldLabel = 'Application version';
                                        validator = (value) => {
                                            if (!value || value.trim() === '') {
                                                return { valid: false, error: 'Version cannot be empty' };
                                            }
                                            // Basic version format validation (e.g., 1.0.0)
                                            if (!/^\d+\.\d+\.\d+/.test(value.trim())) {
                                                return { valid: false, error: 'Invalid version format (expected: x.y.z)' };
                                            }
                                            return { valid: true };
                                        };
                                        break;
                                    case 4:
                                        fieldName = 'description';
                                        fieldLabel = 'Application description';
                                        validator = (value) => {
                                            // Description can be empty, so always valid
                                            return { valid: true };
                                        };
                                        break;
                                    case 5:
                                        fieldName = 'shortcutName';
                                        fieldLabel = 'Application shortcut name';
                                        validator = (value) => {
                                            // ShortcutName can be empty, so always valid
                                            return { valid: true };
                                        };
                                        break;
                                    case 6:
                                        fieldName = 'devToolsEnabled';
                                        fieldLabel = 'Developer Tools';
                                        validator = (value) => {
                                            // Accept true/false, yes/no, 1/0, enable/disable
                                            const lowerValue = value.trim().toLowerCase();
                                            if (['true', 'yes', '1', 'enable', 'enabled'].includes(lowerValue)) {
                                                return { valid: true, normalized: true };
                                            } else if (['false', 'no', '0', 'disable', 'disabled'].includes(lowerValue)) {
                                                return { valid: true, normalized: false };
                                            }
                                            return { valid: false, error: 'Invalid value (use: true/false, yes/no, enable/disable)' };
                                        };
                                        break;
                                }
                                
                                // Special handling for devToolsEnabled (boolean toggle)
                                if (fieldName === 'devToolsEnabled') {
                                    // Show current value
                                    const currentValue = currentConfig[fieldName] !== undefined ? currentConfig[fieldName] : false;
                                    cmd.info(`Current ${fieldLabel}: ${currentValue ? 'Enabled' : 'Disabled'}`);
                                    cmd.info('Note: Enabling DevTools allows you to debug errors in production build.');
                                    cmd.info('      You can access DevTools via F12 or right-click menu.');
                                    cmd.info('      Inspect Element feature is also included (Ctrl+Shift+I).');
                                    
                                    cmd.prompt('Enable Developer Tools? [Y/N]: ', async (answer) => {
                                        const lowerAnswer = answer.trim().toLowerCase();
                                        let newValue;
                                        
                                        if (['y', 'yes', 'true', '1', 'enable'].includes(lowerAnswer)) {
                                            newValue = true;
                                        } else if (['n', 'no', 'false', '0', 'disable'].includes(lowerAnswer)) {
                                            newValue = false;
                                        } else {
                                            cmd.error('✗ Invalid answer! Operation cancelled.');
                                            cmd.startNewCommand();
                                            return;
                                        }
                                        
                                        // Update config with new value
                                        const updatedConfig = {
                                            ...currentConfig,
                                            devToolsEnabled: newValue
                                        };
                                        delete updatedConfig.created_at;
                                        
                                        // Save to config.json via Electron API
                                        if (window.electronAPI && window.electronAPI.saveConfig) {
                                            const result = await window.electronAPI.saveConfig(updatedConfig);
                                            if (result.success) {
                                                cmd.success(`✓ ${fieldLabel} ${newValue ? 'enabled' : 'disabled'} and saved to config.json`);
                                                
                                                // Show created_at timestamp
                                                const timestamp = new Date().toLocaleString('id-ID', {
                                                    year: 'numeric',
                                                    month: '2-digit',
                                                    day: '2-digit',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                    second: '2-digit'
                                                });
                                                cmd.info(`📅 Config updated at: ${timestamp}`);
                                                
                                                // Dispatch event untuk memberitahu bahwa config telah diubah
                                                window.dispatchEvent(new CustomEvent('config-updated', { 
                                                    detail: updatedConfig 
                                                }));
                                                
                                                // Warning: Aplikasi perlu di-reload untuk perubahan config berlaku penuh
                                                cmd.warning('⚠️  Application needs to be reloaded for the changes to take full effect.');
                                                cmd.confirm('Do you want to reload now? [Y/N]', async (reloadConfirmed) => {
                                                    if (reloadConfirmed) {
                                                        cmd.info('🔄 Reloading application...');
                                                        setTimeout(() => {
                                                            window.location.reload();
                                                        }, 500);
                                                    } else {
                                                        cmd.info('⚠️  Please reload manually using Refresh command or Ctrl+R');
                                                        cmd.startNewCommand();
                                                    }
                                                });
                                            } else {
                                                cmd.error(`✗ Failed to save config: ${result.error || 'Unknown error'}`);
                                                cmd.startNewCommand();
                                            }
                                        } else {
                                            cmd.success(`✓ ${fieldLabel} ${newValue ? 'enabled' : 'disabled'} (not saved - not in Electron)`);
                                            cmd.startNewCommand();
                                        }
                                    });
                                    return; // Exit early for devToolsEnabled
                                }
                                
                                // Show current value if exists
                                if (currentConfig[fieldName]) {
                                    cmd.info(`Current ${fieldLabel}: ${currentConfig[fieldName]}`);
                                }
                                
                                cmd.prompt(`Enter new ${fieldLabel}: `, async (newValue) => {
                                    // Allow empty value for description and shortcutName
                                    if (fieldName !== 'description' && fieldName !== 'shortcutName' && (!newValue || newValue.trim() === '')) {
                                        cmd.error('✗ Value is required! Operation cancelled.');
                                        cmd.startNewCommand();
                                        return;
                                    }
                                    
                                    // Validate value
                                    const validation = validator(newValue.trim());
                                    if (!validation.valid) {
                                        cmd.error(`✗ ${validation.error}! Operation cancelled.`);
                                        cmd.startNewCommand();
                                        return;
                                    }
                                    
                                    // Use normalized value if validator returns it (for boolean fields)
                                    const finalValue = validation.normalized !== undefined ? validation.normalized : newValue.trim();
                                    
                                    // Update config with new value (preserve other fields)
                                    // Note: created_at will be automatically set by save-config handler in main.js
                                    const updatedConfig = {
                                        ...currentConfig,
                                        [fieldName]: finalValue
                                    };
                                    // Remove created_at from updatedConfig - it will be set automatically in main.js
                                    // This ensures created_at is always current timestamp, not the old one
                                    delete updatedConfig.created_at;
                                    
                                    // Save to config.json via Electron API
                                    if (window.electronAPI && window.electronAPI.saveConfig) {
                                        const result = await window.electronAPI.saveConfig(updatedConfig);
                                        if (result.success) {
                                            cmd.success(`✓ ${fieldLabel} updated and saved to config.json: ${newValue.trim()}`);
                                            
                                            // Show created_at timestamp
                                            const timestamp = new Date().toLocaleString('id-ID', {
                                                year: 'numeric',
                                                month: '2-digit',
                                                day: '2-digit',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                                second: '2-digit'
                                            });
                                            cmd.info(`📅 Config updated at: ${timestamp}`);
                                            
                                            // Show message about package.json update
                                            if (fieldName === 'version' || fieldName === 'description' || fieldName === 'appName' || fieldName === 'shortcutName') {
                                                cmd.info('✓ package.json also updated automatically');
                                            }
                                            
                                            // Reload config untuk update NEXA object
                                            if (window.reloadConfig) {
                                                await window.reloadConfig();
                                            } else {
                                                // Fallback: Update NEXA object directly for apiDev
                                                if (fieldName === 'apiDev' && window.NEXA) {
                                                    window.NEXA.urlBase = newValue.trim();
                                                    window.NEXA.apiBase = newValue.trim() + '/api';
                                                }
                                            }
                                            
                                            // Dispatch event untuk memberitahu bahwa config telah diubah
                                            window.dispatchEvent(new CustomEvent('config-updated', { 
                                                detail: updatedConfig 
                                            }));
                                            
                                            // Warning: Aplikasi perlu di-reload untuk perubahan config berlaku penuh
                                            if (fieldName === 'apiDev') {
                                                cmd.warning('⚠️  Application needs to be reloaded for the changes to take full effect.');
                                                cmd.confirm('Do you want to reload now? [Y/N]', async (reloadConfirmed) => {
                                                    if (reloadConfirmed) {
                                                        cmd.info('🔄 Reloading application...');
                                                        setTimeout(() => {
                                                            window.location.reload();
                                                        }, 500);
                                                    } else {
                                                        cmd.info('⚠️  Please reload manually using Refresh command or Ctrl+R');
                                                        cmd.startNewCommand();
                                                    }
                                                });
                                            } else {
                                                cmd.startNewCommand();
                                            }
                                        } else {
                                            cmd.error(`✗ Failed to save config: ${result.error || 'Unknown error'}`);
                                            cmd.startNewCommand();
                                        }
                                    } else {
                                        // Fallback jika tidak di Electron
                                        cmd.success(`✓ ${fieldLabel} configured: ${newValue.trim()} (not saved - not in Electron)`);
                                        cmd.startNewCommand();
                                    }
                                });
                            });
                        } catch (error) {
                            console.error('Error during configuration:', error);
                            cmd.error('Failed to complete configuration. Please try again.');
                            cmd.startNewCommand();
                        }
                    });
                });
                
            } catch (error) {
                console.error('Error in config command:', error);
                cmd.error('Failed to execute config command. Please try again.');
                cmd.startNewCommand();
            }
            
            return false;
        }, 'Configure application settings');






        /**
         * Stores Delete - Clear all stores :)
         */
        cmd.addCommand("stores delete", async function storesDeleteHandler() {
            try {
                if (!userDb) {
                    cmd.error('Database not initialized');
                    cmd.startNewCommand();
                    return false;
                }
                
                // Ask for confirmation
                cmd.warning('⚠️  WARNING: This will delete ALL data from ALL stores!');
                cmd.confirm('Are you sure you want to delete all data? [Y/N]', async (confirmed) => {
                    if (!confirmed) {
                        cmd.info('Operation cancelled');
                        cmd.startNewCommand();
                        return;
                    }
                    
                    // Get credential to verify password
                    const credential = await window.NXUI.ref.get("bucketsStore", 'credential');
                    const expectedPassword = credential?.data?.password || null;
                    
                    // Ask for password
                    cmd.secret('Enter your password to continue: ', async (password) => {
                        // Verify password
                        if (expectedPassword && password !== expectedPassword) {
                            cmd.error('✗ Wrong password! Operation cancelled.');
                            cmd.startNewCommand();
                            return;
                        }
                        
                        // If no password in credential, allow with any password or require specific one
                        if (!expectedPassword) {
                            if (!password || password === '') {
                                cmd.error('✗ Password required! Operation cancelled.');
                                cmd.startNewCommand();
                                return;
                            }
                        }
                        
                        try {
                            cmd.info('✓ Password verified. Starting database cleanup...');
                            
                            // Get stores list
                            const stores = userDb.getDefaultStores ? userDb.getDefaultStores() : [];
                            let totalDeleted = 0;
                        
                        // Delete all data from each store
                        for (let i = 0; i < stores.length; i++) {
                            const store = stores[i];
                            
                            try {
                                cmd.info(`Clearing store: ${store}...`);
                                
                                // Try to clear store using IndexedDB directly first (more reliable)
                                // BUT: Skip clear() for bucketsStore to protect credential
                                let clearedWithClearMethod = false;
                                if (store !== 'bucketsStore') {
                                    try {
                                        if (userDb && userDb.db) {
                                            const transaction = userDb.db.transaction([store], 'readwrite');
                                            const objectStore = transaction.objectStore(store);
                                            
                                            // Count items before clearing
                                            const countBefore = await new Promise((resolve, reject) => {
                                                const countRequest = objectStore.count();
                                                countRequest.onsuccess = () => resolve(countRequest.result || 0);
                                                countRequest.onerror = () => reject(countRequest.error);
                                            });
                                            
                                            // Clear the store
                                            const clearRequest = objectStore.clear();
                                            
                                            await new Promise((resolve, reject) => {
                                                clearRequest.onsuccess = () => {
                                                    totalDeleted += countBefore;
                                                    resolve();
                                                };
                                                clearRequest.onerror = () => {
                                                    reject(clearRequest.error);
                                                };
                                            });
                                            
                                            cmd.info(`  ✓ Cleared ${store} (${countBefore} items) using clear() method`);
                                            clearedWithClearMethod = true;
                                        }
                                    } catch (clearError) {
                                        // If clear() fails, fall back to individual delete method
                                        console.log(`Clear() failed for ${store}, using individual delete:`, clearError);
                                    }
                                } else {
                                    // For bucketsStore, always use individual delete to protect credential
                                    cmd.info(`  ⚠️  Using individual delete for ${store} to protect credential`);
                                }
                                
                                // If already cleared with clear() method, skip individual delete
                                if (clearedWithClearMethod) {
                                    continue;
                                }
                                
                                // Get all data from store
                                const storeData = await window.NXUI.ref.getAll(store);
                                
                                // Special handling for bucketsStore to protect credential
                                if (store === 'bucketsStore') {
                                    cmd.info(`  🔒 Protecting credential in ${store}`);
                                }
                                
                                if (storeData) {
                                    let itemsToDelete = [];
                                    
                                    // Get store config to determine keyPath
                                    const storeConfig = userDb.getStoreConfig ? userDb.getStoreConfig(store) : null;
                                    const keyPath = storeConfig?.keyPath || 'id'; // Default to 'id'
                                    
                                    // Extract all keys/items to delete
                                    if (Array.isArray(storeData)) {
                                        itemsToDelete = storeData.map(item => {
                                            // Try to get key from keyPath, or fallback to common key names
                                            if (item && typeof item === 'object') {
                                                // For fileContents, prioritize fileId, then filePath, then keyPath
                                                if (store === 'fileContents') {
                                                    return item.fileId || item.filePath || item.path || item[keyPath];
                                                }
                                                return item[keyPath] || item.id || item.key || item.fileId || item.path || item;
                                            }
                                            return item;
                                        }).filter(key => key !== null && key !== undefined);
                                    } else if (typeof storeData === 'object') {
                                        if (storeData.data && Array.isArray(storeData.data)) {
                                            itemsToDelete = storeData.data.map(item => {
                                                if (item && typeof item === 'object') {
                                                    // For fileContents, prioritize fileId, then filePath, then keyPath
                                                    if (store === 'fileContents') {
                                                        return item.fileId || item.filePath || item.path || item[keyPath];
                                                    }
                                                    return item[keyPath] || item.id || item.key || item.fileId || item.path || item;
                                                }
                                                return item;
                                            }).filter(key => key !== null && key !== undefined);
                                        } else {
                                            // If it's a plain object, use keys directly
                                            itemsToDelete = Object.keys(storeData);
                                        }
                                    }
                                    
                                    // Log for debugging
                                    if (store === 'fileContents' && itemsToDelete.length > 0) {
                                        console.log(`[${store}] Keys to delete:`, itemsToDelete);
                                    }
                                    
                                    // Delete each item (except credential from bucketsStore)
                                    for (const key of itemsToDelete) {
                                        try {
                                            // Skip deleting credential from bucketsStore
                                            if (store === 'bucketsStore' && key === 'credential') {
                                                cmd.info(`  ⚠️  Skipping credential (protected)`);
                                                continue;
                                            }
                                            
                                            await window.NXUI.ref.delete(store, key);
                                            totalDeleted++;
                                        } catch (deleteError) {
                                            console.error(`Error deleting ${key} from ${store}:`, deleteError);
                                            // Try alternative delete method for fileContents
                                            if (store === 'fileContents') {
                                                try {
                                                    // Try using filePath if fileId failed
                                                    const item = Array.isArray(storeData) ? storeData.find(i => i.fileId === key || i.filePath === key) : null;
                                                    if (item && item.filePath && item.filePath !== key) {
                                                        await window.NXUI.ref.delete(store, item.filePath);
                                                        totalDeleted++;
                                                        console.log(`Deleted ${store} using filePath: ${item.filePath}`);
                                                    }
                                                } catch (altError) {
                                                    console.error(`Alternative delete also failed for ${key}:`, altError);
                                                }
                                            }
                                        }
                                    }
                                }
                            } catch (storeError) {
                                console.error(`Error processing store ${store}:`, storeError);
                                cmd.warning(`⚠️  Error processing store: ${store}`);
                            }
                        }
                        
                            cmd.success(`✓ Database cleanup completed! Deleted ${totalDeleted} items from ${stores.length} stores.`);
                            
                            setTimeout(() => {
                                cmd.info('Reloading page...');
                                setTimeout(() => {
                                    window.location.reload();
                                }, 500);
                            }, 1000);
                            
                        } catch (error) {
                            console.error('Error during database cleanup:', error);
                            cmd.error('Failed to complete database cleanup. Please try again.');
                            cmd.startNewCommand();
                        }
                    });
                });
                
            } catch (error) {
                console.error('Error in stores delete command:', error);
                cmd.error('Failed to execute delete command. Please try again.');
                cmd.startNewCommand();
            }
            
            return false;
        }, 'Delete all data from all stores and reload page');
        /**
         * App Stop :)
         */
        cmd.addCommand("stop", async function appStopHandler() {
            try {
                cmd.info('Stopping application...');
                
                setTimeout(() => {
                    cmd.info('Closing modules...');
                    setTimeout(() => {
                        cmd.info('Shutting down services...');
                        setTimeout(async () => {
                            // Update credential to set applications to false
                            const updateData = {
                                applications: false,
                                updatedAt: new Date().toISOString()
                            };
                            
                            await window.NXUI.ref.mergeData("bucketsStore", "credential", updateData, {
                                deepMerge: true,
                                createIfNotExists: true
                            });
                            
                            // Update userCredential in closure
                            const verifiedCredential = await window.NXUI.ref.get("bucketsStore", 'credential');
                            if (verifiedCredential) {
                                userCredential = {
                                    credential: verifiedCredential,
                                    oauth: verifiedCredential.oauth || false,
                                    applications: verifiedCredential.applications || false,
                                    data: verifiedCredential.data || null
                                };
                            }
                            
                            cmd.success('Application stopped successfully!');
                            
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        }, 800);
                    }, 800);
                }, 800);
                
            } catch (error) {
                console.error('Error stopping application:', error);
                cmd.error('Failed to stop application. Please try again.');
                cmd.startNewCommand();
            }
            
            return false;
        }, 'Stop the application');
        /**
         * App Stop :)
         */
        cmd.addCommand("access", async function appStopHandler() {
            try {
                cmd.info('Stopping application...');
                
                setTimeout(() => {
                    cmd.info('Closing modules...');
                    setTimeout(() => {
                        cmd.info('Shutting down services...');
                        setTimeout(async () => {
                            // Update credential to set applications to false
                         
                          const userAgent = await window.NXUI.ref.get("bucketsStore", "userAgent");
                          console.log('userAgent:', userAgent);
                            
                            // Display user agent information using JsonViewer
                            if (userAgent) {
                                cmd.info('📋 User Access Information:');
                                
                                // Create json-viewer element
                                const jsonViewer = document.createElement('json-viewer');
                                jsonViewer.setAttribute('data', JSON.stringify(userAgent));
                                
                                // Apply theme (light theme)
                                if (typeof applyTheme === 'function') {
                                    applyTheme(jsonViewer, 'light');
                                } else {
                                    // Fallback: add light class directly
                                    jsonViewer.classList.add('light');
                                }
                                
                                // Create container for json viewer
                                const container = document.createElement('div');
                                container.style.marginTop = '10px';
                                container.style.marginBottom = '10px';
                                container.appendChild(jsonViewer);
                                
                                // Append to terminal output
                                const outputElement = cmd.container.querySelector('.command-line-output:last-child') || cmd.container;
                                outputElement.appendChild(container);
                            }
                            
                            cmd.success('Application access successfully!');
                            cmd.startNewCommand();
                     
                        }, 800);
                    }, 800);
                }, 800);
                
            } catch (error) {
                console.error('Error stopping application:', error);
                cmd.error('Failed to stop application. Please try again.');
                cmd.startNewCommand();
            }
            
            return false;
        }, 'Stop the application');

        /**
         * App Ping - Check Status :)
         */
        cmd.addCommand("ping", async function appPingHandler() {
            try {
                // Get current credential from IndexedDB
                const credential = await window.NXUI.ref.get("bucketsStore", 'credential');
                
                if (credential) {
                    const status = {
                        applications: credential.applications !== undefined ? credential.applications : false,
                        oauth: credential.oauth !== undefined ? credential.oauth : false,
                        updatedAt: credential.updatedAt || 'N/A'
                    };
                    // Use enhanced list with tree style for better visualization
                    cmd.info('Pong Status:');
                    const statusList = [
                        `applications: ${status.applications ? '<span class="status-true">✓ true</span>' : '<span class="status-false">✗ false</span>'}`,
                        `oauth: ${status.oauth ? '<span class="status-true">✓ true</span>' : '<span class="status-false">✗ false</span>'}`,
                        `updatedAt: ${status.updatedAt}`
                    ];
                    
                    cmd.listEnhanced(statusList, 1, {
                        style: 'tree',
                        highlight: true,
                        className: 'status-list'
                    });
                    
                    setTimeout(() => {
                        cmd.success('Status retrieved successfully');
                        cmd.startNewCommand();
                    }, 100);
                } else {
                    cmd.error('Credential not found');
                }
                
            } catch (error) {
                console.error('Error checking application status:', error);
                cmd.error('Failed to check application status. Please try again.');
            }
            
            return false;
        }, 'Check application status');


         // cmd.addCommand("test", async function () {
         //         const nexaNpm = new NexaNpm();
         //         await nexaNpm.init();
         //         const html = nexaNpm.render();
         //         cmd.output(html);
         //         cmd.startNewCommand();
         //         return false;
         // }, 'Test NexaNpm component');

        /**
         * Upgrade NexaUI - Update assets/NexaUi folder
         */
        cmd.addCommand("upgrade", async function upgradeHandler() {
            try {
                // Check if electronAPI is available
                if (!window.electronAPI || !window.electronAPI.checkNexaUiUpdate) {
                    cmd.error('✗ Electron API not available. This command only works in Electron app.');
                    cmd.startNewCommand();
                    return false;
                }
                // Get URL from config (required)
                let serverUrl = null;
                try {
                    if (window.electronAPI && window.electronAPI.getConfig) {
                        const configResult = await window.electronAPI.getConfig();
                        if (configResult.success && configResult.config && configResult.config.apiDev) {
                            // Use apiDev as base URL for update server
                            const baseUrl = configResult.config.apiDev.replace(/\/$/, '');
                            serverUrl = `${baseUrl}/api/version`;
                        }
                    }
                } catch (configError) {
                    // Config read failed
                }
                // Check if serverUrl is available
                if (!serverUrl) {
                    cmd.error('✗ Update server URL not configured');
                    cmd.info('   Please configure apiDev in config.json first');
                    cmd.info('   Use command: config');
                    cmd.startNewCommand();
                    return false;
                }
                
                try {
                    // Check for updates
                    // cmd.info(`📡 Connecting to: ${serverUrl}`);
                    const updateInfo = await window.electronAPI.checkNexaUiUpdate(serverUrl);

                    if (!updateInfo.available) {
                        if (updateInfo.error) {
                            cmd.error(`✗ Error checking update: ${updateInfo.error}`);
                        } else {
                            cmd.info('✓ You are already using the latest version of NexaUI');
                        }
                        cmd.startNewCommand();
                        return false;
                    }

                    // Display update information
                    cmd.success(`✓ NexaUI v${updateInfo.version}`);
                    cmd.info('📋 Update Information:');
                    cmd.info(`   Version: ${updateInfo.version}`);
                    if (updateInfo.changelog) {
                        cmd.info(`   Changelog: ${updateInfo.changelog}`);
                    }
                    // Ask for confirmation
                    cmd.warning('⚠️  This will update the NexaUI framework files.');
                    cmd.warning('⚠️  A backup will be created automatically.');
                    cmd.confirm('Do you want to proceed with the update? [Y/N]', async (confirmed) => {
                        if (!confirmed) {
                            cmd.info('Operation cancelled');
                            cmd.startNewCommand();
                            return;
                        }

                        try {
                            // Show progress using NexaNpm
                            const nexaNpm = new NexaNpm();
                            await nexaNpm.init();
                            const npmHtml = nexaNpm.render();
                            
                            // Create container for NexaNpm
                            const npmContainer = document.createElement('div');
                            npmContainer.innerHTML = npmHtml;
                            npmContainer.style.marginTop = '10px';
                            npmContainer.style.marginBottom = '10px';
                            
                            // Append to terminal container
                            cmd.container.appendChild(npmContainer);

                            // Download and install update
                            const result = await window.electronAPI.updateNexaUi(
                                updateInfo.downloadUrl,
                                updateInfo.version
                            );

                            // Cleanup NexaNpm
                            nexaNpm.destroy();
                            if (npmContainer.parentNode) {
                                npmContainer.parentNode.removeChild(npmContainer);
                            }
                            // cmd.info('');

                            if (result.success) {
                                cmd.success(`✓ Update successful! NexaUI v${result.version} installed`);
                                if (result.backupPath) {
                                    // cmd.info(`   Backup saved to: ${result.backupPath}`);
                                }
                                
                                // Reload config to update version in window.NEXA
                                try {
                                    if (window.reloadConfig) {
                                        await window.reloadConfig();
                                        cmd.info(`✓ Config updated to version ${result.version}`);
                                    }
                                } catch (reloadError) {
                                    console.warn('Failed to reload config:', reloadError);
                                }
                                cmd.warning('⚠️  Please restart the application to apply changes.');
                                cmd.confirm('Restart now? [Y/N]', async (restart) => {
                                    if (restart) {
                                        cmd.info('🔄 Restarting application...');
                                        setTimeout(() => {
                                            window.location.reload();
                                        }, 1000);
                                    } else {
                                        cmd.info('You can restart manually later.');
                                        cmd.startNewCommand();
                                    }
                                });
                            } else {
                                cmd.error(`✗ Update failed: ${result.error || 'Unknown error'}`);
                                cmd.info('Please check the error message and try again.');
                                cmd.startNewCommand();
                            }
                        } catch (updateError) {
                            cmd.error(`✗ Error during update: ${updateError.message}`);
                            cmd.startNewCommand();
                        }
                    });

                } catch (checkError) {
                    cmd.error(`✗ Failed to check for updates: ${checkError.message}`);
                    cmd.startNewCommand();
                }

                return false;
            } catch (error) {
                console.error('Error in upgrade command:', error);
                cmd.error('Failed to check for updates. Please try again.');
                cmd.startNewCommand();
                return false;
            }
        }, 'Upgrade NexaUI framework to latest version');

        /**
         * Apply theme to json-viewer element
         * @param {HTMLElement} jsonViewer - The json-viewer element
         * @param {string} themeName - Theme name ('light' or 'dark')
         */
        function applyTheme(jsonViewer, themeName = 'light') {
            // Remove existing theme classes
            jsonViewer.classList.remove('light', 'dark');
            
            // Add the appropriate theme class based on jsonbundle.css
            if (themeName === 'light') {
                jsonViewer.classList.add('light');
            }
            // Default is dark theme (no class needed as it's the default in CSS)
        }

        /**
         * Format AI response for terminal display
         * Removes HTML tags, simplifies markdown, and makes it terminal-friendly
         */
        function formatTerminalResponse(text) {
            if (!text) return '';
            
            let formatted = text;
            
            // Remove HTML tags
            formatted = formatted.replace(/<[^>]*>/g, '');
            
            // Decode HTML entities
            formatted = formatted
                .replace(/&lt;/g, '<')
                .replace(/&gt;/g, '>')
                .replace(/&amp;/g, '&')
                .replace(/&quot;/g, '"')
                .replace(/&#39;/g, "'")
                .replace(/&nbsp;/g, ' ')
                .replace(/&apos;/g, "'");
            
            // Simplify markdown headers
            formatted = formatted.replace(/^#{1,6}\s+(.+)$/gm, '$1:');
            
            // Simplify bold/italic markdown
            formatted = formatted.replace(/\*\*([^*]+)\*\*/g, '$1');
            formatted = formatted.replace(/\*([^*]+)\*/g, '$1');
            formatted = formatted.replace(/__([^_]+)__/g, '$1');
            formatted = formatted.replace(/_([^_]+)_/g, '$1');
            
            // Simplify code blocks - keep content but remove markdown
            formatted = formatted.replace(/```[\w]*\n?([\s\S]*?)```/g, (match, code) => {
                return '\n[CODE]\n' + code.trim() + '\n[/CODE]\n';
            });
            
            // Simplify inline code
            formatted = formatted.replace(/`([^`]+)`/g, '[$1]');
            
            // Simplify lists - convert to simple bullet points
            formatted = formatted.replace(/^[\*\-\+]\s+(.+)$/gm, '  • $1');
            formatted = formatted.replace(/^\d+\.\s+(.+)$/gm, '  $1');
            
            // Simplify links - show text only
            formatted = formatted.replace(/\[([^\]]+)\]\([^\)]+\)/g, '$1');
            
            // Simplify blockquotes
            formatted = formatted.replace(/^>\s+(.+)$/gm, '  | $1');
            
            // Clean up multiple newlines (max 2 consecutive)
            formatted = formatted.replace(/\n{3,}/g, '\n\n');
            
            // Trim whitespace
            formatted = formatted.trim();
            
            return formatted;
        }

        /**
         * Help :)
         */
        cmd.addCommand("help", function () {
            const helpResults = [];
            // List of secret commands to hide from help
            const secretCommands = ['config', 'db', 'stores delete', 'nexa app','pckg'];
            
            for (let command = 0; command < cmd.commands.length; command++) {
                // Hide secret commands from help list
                const commandName = cmd.commands[command].resolved.getSignature();
                const isSecret = secretCommands.some(secret => 
                    commandName && commandName.toLowerCase().includes(secret.toLowerCase())
                );
                
                if (isSecret) {
                    continue; // Skip secret commands
                }
                helpResults.push(commandName);
                helpResults.push(cmd.commands[command].description);
            }
            cmd.list(helpResults, 2);
        }, "Show available commands");

        /**
         * Clear terminal screen
         */
        cmd.addCommand("clear", function () {
            cmd.container.innerHTML = '';
            cmd.stop();
        }, 'Clear terminal screen');



    



        /**
         * Clear terminal screen (alias for clear)
         */
        cmd.addCommand("cls", function () {
            cmd.container.innerHTML = '';
            cmd.stop();
        }, 'Clear terminal screen (alias for clear)');

        /**
         * Show user access information
         */
        cmd.addCommand("user", async function () {
            try {
                const userAgent = await window.NXUI.ref.get("bucketsStore", "userAgent");
                
                if (!userAgent) {
                    cmd.error('User access information not found');
                    cmd.startNewCommand();
                    return;
                }
                
                cmd.info('📋 User Access Information:');
                
                // Create json-viewer element
                const jsonViewer = document.createElement('json-viewer');
                jsonViewer.setAttribute('data', JSON.stringify(userAgent));
                
                // Apply light theme
                jsonViewer.classList.remove('light', 'dark');
                jsonViewer.classList.add('light');
                
                // Create container for json viewer
                const container = document.createElement('div');
                container.style.marginTop = '10px';
                container.style.marginBottom = '10px';
                container.style.width = '100%';
                container.appendChild(jsonViewer);
                
                // Append to terminal output
                const outputElement = cmd.container.querySelector('.command-line-output:last-child') || cmd.container;
                outputElement.appendChild(container);
                
                cmd.success('User information displayed');
                cmd.startNewCommand();
                
            } catch (error) {
                console.error('Error getting user information:', error);
                cmd.error('Failed to get user information. Please try again.');
                cmd.startNewCommand();
            }
        }, 'Show user access information');

        /**
         * List all files
         */
        cmd.addCommand("ls", function () {
            cmd.list([
                'Applications',
                'User Information',
                'Library',
                'Users',
                'Volumes',
                'etc',
                'Users',
                'home',
                'var',
                'System'
            ]);
        }, 'List directory files');

        /**
         * List all files
         */
        cmd.addCommand("date", function () {
            cmd.output(new Date());
            cmd.commandRow.hideTime();
        }, 'show current DateTime');

        /**
         * Show current user
         */
        cmd.addCommand("whoami", function () {
            cmd.output(cmd.username);
            cmd.commandRow.hideTime();
        }, 'show current Username');

        /**
         * Show current user
         */
        cmd.addCommand("shortcuts", function () {
            cmd.list([
                'Ctrl + c :  Cancel Command',
                'Ctrl + r :  Clear Screen',
                'Arrow up :  Previous command',
                'Arrow Down :  Next Command',
                'Tab :  Autocomplete command'
            ], 1);
        }, 'Show keyboard short cuts');

        /**
         * AI Chat - Chat with AI using Gemini :)
         */
        // Register command with wildcard to accept any text after "ai"
        cmd.addCommand("ai question*", async function aiChatHandler(command) {
            try {
                // Use shared NexaAI instance to maintain conversation history
                if (!window.nexaAIInstance) {
                    window.nexaAIInstance = new NexaAI();
                }
                const nexaAI = window.nexaAIInstance;
                
                // Get question from command argument (wildcard will capture all text after "ai")
                let question = '';
                
                // Get from argument (wildcard argument name is "question" without *)
                try {
                    question = command.getArgument('question') || '';
                } catch (e) {
                    // If argument not found, get from segments as fallback
                    const commandSegments = command.getCommandSegments();
                    if (commandSegments && commandSegments.length > 0) {
                        question = commandSegments.join(' ').trim();
                    }
                }
                
                // Initialize chatConfig if not already available
                if (!nexaAI.isConfigured()) {
                    try {
                        cmd.info('Initializing AI chat...');
                        await nexaAI.init();
                        cmd.success('✓ AI chat initialized');
                    } catch (initError) {
                        console.error('Error initializing chat:', initError);
                        cmd.error('Failed to initialize AI chat. Please try again.');
                        cmd.startNewCommand();
                        return false;
                    }
                } else {
                    // Use existing config if available
                    if (window.chatConfig) {
                        nexaAI.chatConfig = window.chatConfig;
                        nexaAI.initialized = true;
                    } else {
                        await nexaAI.init();
                    }
                }
                
                // If question provided, process it directly
                if (question && question.trim() !== '') {
                    await sendAIRequest(question.trim());
                } else {
                    // If no question provided, prompt for it
                    cmd.prompt('Ask AI a question: ', async (userQuestion) => {
                        if (!userQuestion || userQuestion.trim() === '') {
                            cmd.info('No question provided. Exiting...');
                            cmd.startNewCommand();
                            return;
                        }
                        
                        await sendAIRequest(userQuestion.trim());
                    });
                    return false;
                }
                
                async function sendAIRequest(questionText) {
                    try {
                        // Check if question is asking for user list
                        if (nexaAI.isAskingForUserList(questionText)) {
                            const friends = nexaAI.getFriendsList();
                            const currentUser = nexaAI.getCurrentUser();
                            
                            if (friends.length === 0) {
                                cmd.info('📋 Daftar User:');
                                cmd.output('Tidak ada data user yang tersedia.');
                                cmd.startNewCommand();
                                return;
                            }
                            
                            cmd.info('📋 Daftar User:');
                            
                            // Prepare user list for display
                            const userList = [];
                            
                            // Add current user first if available
                            if (currentUser) {
                                userList.push(`👤 ${currentUser.nama} (${currentUser.jabatan}) - ID: ${currentUser.id} [Anda]`);
                            }
                            
                            // Add other users
                            friends.forEach((user, index) => {
                                const isLast = index === friends.length - 1;
                                const treeChar = isLast ? '└──' : '├──';
                                userList.push(`     ${treeChar} ${user.nama} (${user.jabatan}) - ID: ${user.id}`);
                            });
                            
                            cmd.listEnhanced(userList, 1, {
                                style: 'tree',
                                highlight: true,
                                className: 'status-list'
                            });
                            
                            cmd.startNewCommand();
                            return;
                        }
                        
                        cmd.info('🤖 Thinking...');
                        
                        // Use NexaAI to send request
                        const aiResponse = await nexaAI.ask(questionText);
                        
                        // Format response for terminal display
                        const formattedResponse = formatTerminalResponse(aiResponse);
                        
                        // Display AI response
                        cmd.info('🤖 AI Response:');
                        cmd.output(formattedResponse);
                        cmd.startNewCommand();
                        
                    } catch (error) {
                        console.error('Error calling AI:', error);
                        cmd.error(`Failed to get AI response: ${error.message}`);
                        cmd.startNewCommand();
                    }
                }
                
            } catch (error) {
                console.error('Error in AI chat command:', error);
                cmd.error('Failed to execute AI chat. Please try again.');
                cmd.startNewCommand();
            }
            
            return false;
        }, 'Chat with AI (usage: ai [question] or just "ai" to enter interactive mode)');

        return cmd;
    };

})();
