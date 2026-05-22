// Export function untuk route 'contact/data' (menjadi 'contact_data.js')
export async function blog(page, route) {
  route.register(page, async (routeName, container, routeMeta = {
    title: "Contact Data | App",
    description: "Data kontak.",
  }, style, nav = {}) => {
    route.routeMetaByRoute.set(page, routeMeta);
    console.log("📍 Navigating to:", NEXA);
const components = [
  {
    id: 1,
    version: "1.0.0",
    label: "alerts",
    icon: "bell",
    description: "Alert dan notification UI components",
    category: "ui",
    file: ["index.html", "index.css"],
    status: true,
    created_at: "2025-01-01",
  },
];
 const html = NXUI.div().id('tes').class('bold2').container()

  .div('container').id('level')
    .h1().class('bold').view('ini h1')
    .h2().view('ini h4')
    .div().container()
      .map(components, (item) =>{
         const status = item.id === 1 ? 'Active' : 'Not';

       return  NXUI.div().container()
           .h1().onclick(`buatPackage('${item.id}')`).view(item.label)
           .div().a('http://localhost/').fs('31px').view(item.version)
         .div().btn('openModal()').class('btn-primary').view('Buka Modal')
         .icon('octicon octicon-mark-github-16').id('aaaaaaa').color('#24292f')  
         .small().class('sss').view(status)
         .end() 

      })
    .end()                             // ← kembali ke div#level
  .end()  
   .icon('octicon octicon-mark-github-16').id('aaaaaaa').color('#24292f')  
  .div('container').class('images').img('http://localhost/assets/images/logo.png').wh('20x20px').end()
  .div('container').a('http://localhost/').class('bolds').view('Multiple')
  .span().color('#bc4c00').view('hello world 2').pre('code')
   container.innerHTML = `${html}`;
  });
}
nx.buatPackage =async function (key) {
   console.log('key:', key);
}
