import{r as g,l as j,c as l,a as t,m as N,b as z,F as h,p as v,d as c,w as D,t as n,f as _,q as B,o as i,n as S}from"./index-qVXh2MOx.js";const A={class:"max-w-7xl mx-auto space-y-6"},O={class:"bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"},M={key:0,class:"p-8 text-center text-gray-500"},H={key:1,class:"p-12 text-center"},R={key:2,class:"overflow-x-auto"},I={class:"w-full text-left text-sm"},V={class:"divide-y divide-gray-50"},L={class:"px-6 py-4 font-mono text-gray-500"},E={class:"px-6 py-4 text-gray-700"},K={class:"text-xs text-gray-400"},P={class:"px-6 py-4"},q={class:"font-medium text-gray-900"},F={class:"text-xs text-gray-500"},J={class:"px-6 py-4 text-gray-600 max-w-xs truncate"},Q={class:"px-6 py-4 font-bold text-gray-800"},W={class:"px-6 py-4"},Y={class:"flex flex-col gap-1"},U={key:0,class:"inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 w-fit"},G={key:2,class:"text-xs text-gray-500 italic truncate max-w-[150px]"},X={class:"px-6 py-4 text-right space-x-2"},Z=["onClick"],tt=["onClick"],et={class:"bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]"},st={class:"px-6 py-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center"},at={class:"font-bold text-lg text-gray-800"},nt={class:"p-6 overflow-y-auto space-y-4"},ot={class:"flex justify-between items-start"},it={class:"font-medium text-lg"},lt={class:"text-sm text-gray-500"},dt={class:"text-right"},rt={class:"font-medium"},pt={class:"text-xs text-gray-500"},ct={class:"space-y-3"},xt={class:"font-medium"},mt={class:"text-xs text-gray-500 ml-2"},ut={key:0,class:"text-gray-400"},yt={class:"font-medium"},gt={class:"bg-gray-50 p-4 rounded-xl space-y-2 text-sm"},ht={class:"flex justify-between font-bold text-gray-800 text-lg"},vt={class:"flex justify-between text-gray-600"},_t={class:"capitalize"},ft={key:0,class:"text-xs text-gray-500 flex justify-between border-t border-gray-200 pt-1 mt-1"},bt={key:1,class:"text-gray-500 italic pt-1"},Tt={__name:"ArchiveOrders",setup(wt){const f=g(!0),x=g([]),o=g(null);j(async()=>{try{const e=await(await fetch("/api/Beauty_Salon/Orders?status=completed")).json();e.success&&(x.value=e.data)}catch(s){console.error(s)}finally{f.value=!1}});function w(s){o.value=s}function d(s){return new Intl.NumberFormat("id-ID").format(s||0)}function b(s){const e=window.open("","_blank","width=400,height=600");let a="";s.order_items&&s.order_items.length>0&&s.order_items.forEach(y=>{const $=y.qty||1,C=d(y.price);a+=`
                <tr>
                    <td style="padding: 2px 0;">${y.product_name}</td>
                    <td style="padding: 2px 0; text-align: center;">${$}</td>
                    <td style="padding: 2px 0; text-align: right;">${C}</td>
                </tr>
            `});let r="";s.payment_method==="split"?r=`Split (Tunai: ${d(s.pay_cash)} / Non-Tunai: ${d(s.pay_non_cash)})`:s.payment_method==="non_tunai"?r="Non Tunai (Transfer/QRIS)":r="Tunai";const p=m(s.completed_at||s.order_date||s.created_at),k=u(s.completed_at||s.order_date||s.created_at),T=`
<!DOCTYPE html>
<html>
<head>
    <title>Nota #${s.id}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: 58mm;
            padding: 5mm;
            background: white;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .header p {
            font-size: 10px;
            color: #333;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 2px;
        }
        table {
            width: 100%;
            font-size: 11px;
            border-collapse: collapse;
        }
        .total-section {
            margin-top: 8px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            font-weight: bold;
            margin-top: 4px;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 10px;
        }
        @media print {
            body { width: 58mm; }
            @page { size: 58mm auto; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>BEAUTY SALON</h1>
        <p>Jl. Contoh Alamat No. 123</p>
        <p>Telp: 08xx-xxxx-xxxx</p>
    </div>
    
    <div class="divider"></div>
    
    <div class="info-row">
        <span>No: #${s.id}</span>
        <span>${p}</span>
    </div>
    <div class="info-row">
        <span>Kasir: Admin</span>
        <span>${k}</span>
    </div>
    <div class="info-row">
        <span>Customer:</span>
        <span>${s.customer_name||"-"}</span>
    </div>
    
    <div class="divider"></div>
    
    <table>
        <thead>
            <tr style="border-bottom: 1px solid #000;">
                <th style="text-align: left; padding-bottom: 4px;">Item</th>
                <th style="text-align: center; padding-bottom: 4px;">Qty</th>
                <th style="text-align: right; padding-bottom: 4px;">Harga</th>
            </tr>
        </thead>
        <tbody>
            ${a}
        </tbody>
    </table>
    
    <div class="divider"></div>
    
    <div class="total-section">
        <div class="total-row">
            <span>TOTAL</span>
            <span>Rp ${d(s.total_price)}</span>
        </div>
        <div class="info-row" style="margin-top: 6px;">
            <span>Bayar:</span>
            <span>${r}</span>
        </div>
        ${s.payment_notes?`<div class="info-row"><span>Catatan:</span><span>${s.payment_notes}</span></div>`:""}
    </div>
    
    <div class="divider"></div>
    
    <div class="footer">
        <p class="bold">Terima Kasih</p>
        <p>Atas Kunjungan Anda</p>
        <p style="margin-top: 5px;">~ Sampai Jumpa Kembali ~</p>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        }
    <\/script>
</body>
</html>
    `;e.document.write(T),e.document.close()}function m(s){return s?new Date(s).toLocaleDateString("id-ID",{day:"numeric",month:"short",year:"numeric"}):"-"}function u(s){return s?new Date(s).toLocaleTimeString("id-ID",{hour:"2-digit",minute:"2-digit"}):""}return(s,e)=>(i(),l("div",A,[e[14]||(e[14]=t("div",{class:"bg-white rounded-xl shadow-sm border border-gray-100 px-6 py-4 flex items-center justify-between"},[t("div",null,[t("h2",{class:"text-lg font-bold text-gray-800"},"Arsip Order Selesai"),t("p",{class:"text-sm text-gray-500"},"Riwayat transaksi yang telah diselesaikan")]),t("div",{class:"flex gap-2"})],-1)),t("div",O,[f.value?(i(),l("div",M,"Memuat arsip...")):x.value.length===0?(i(),l("div",H,[...e[3]||(e[3]=[z('<div class="inline-block p-4 rounded-full bg-gray-50 mb-4"><svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg></div><h3 class="text-lg font-medium text-gray-900">Belum ada arsip</h3><p class="text-gray-500">Order yang diselesaikan akan muncul di sini.</p>',3)])])):(i(),l("div",R,[t("table",I,[e[5]||(e[5]=t("thead",{class:"bg-gray-50 border-b border-gray-100 text-gray-500 uppercase tracking-wider text-xs"},[t("tr",null,[t("th",{class:"px-6 py-4 font-semibold"},"Order ID"),t("th",{class:"px-6 py-4 font-semibold"},"Tanggal Selesai"),t("th",{class:"px-6 py-4 font-semibold"},"Pelanggan"),t("th",{class:"px-6 py-4 font-semibold"},"Layanan"),t("th",{class:"px-6 py-4 font-semibold"},"Total"),t("th",{class:"px-6 py-4 font-semibold"},"Pembayaran"),t("th",{class:"px-6 py-4 font-semibold text-right"},"Aksi")])],-1)),t("tbody",V,[(i(!0),l(h,null,v(x.value,a=>(i(),l("tr",{key:a.id,class:"hover:bg-gray-50/50 transition"},[t("td",L,"#"+n(a.id),1),t("td",E,[t("div",null,n(m(a.completed_at||a.updated_at)),1),t("div",K,n(u(a.completed_at||a.updated_at)),1)]),t("td",P,[t("div",q,n(a.customer_name),1),t("div",F,n(a.customer_phone),1)]),t("td",J,n(a.order_items.map(r=>r.product_name).join(", ")),1),t("td",Q," Rp "+n(d(a.total_price)),1),t("td",W,[t("div",Y,[a.payment_method==="split"?(i(),l("span",U," Split (T: "+n(d(a.pay_cash))+" / N: "+n(d(a.pay_non_cash))+") ",1)):(i(),l("span",{key:1,class:S(["inline-flex items-center px-2 py-0.5 rounded text-xs font-medium w-fit",a.payment_method==="non_tunai"?"bg-blue-100 text-blue-800":"bg-green-100 text-green-800"])},n(a.payment_method==="non_tunai"?"Non Tunai":"Tunai"),3)),a.payment_notes?(i(),l("span",G,n(a.payment_notes),1)):c("",!0)])]),t("td",X,[t("button",{onClick:r=>b(a),class:"text-gray-600 hover:text-gray-800 font-medium text-xs inline-flex items-center gap-1"},[...e[4]||(e[4]=[t("svg",{class:"w-3.5 h-3.5",fill:"none",stroke:"currentColor",viewBox:"0 0 24 24"},[t("path",{"stroke-linecap":"round","stroke-linejoin":"round","stroke-width":"2",d:"M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"})],-1),_(" Cetak ",-1)])],8,Z),t("button",{onClick:r=>w(a),class:"text-pink-600 hover:text-pink-800 font-medium text-xs"},"Detail",8,tt)])]))),128))])])]))]),(i(),N(B,{to:"body"},[o.value?(i(),l("div",{key:0,class:"fixed inset-0 z-[1001] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm",onClick:e[2]||(e[2]=D(a=>o.value=null,["self"]))},[t("div",et,[t("div",st,[t("h3",at,"Detail Order #"+n(o.value.id),1),t("button",{onClick:e[0]||(e[0]=a=>o.value=null),class:"text-gray-400 hover:text-gray-600"},"✕")]),t("div",nt,[t("div",ot,[t("div",null,[e[6]||(e[6]=t("div",{class:"text-xs text-gray-500 uppercase font-bold"},"Pelanggan",-1)),t("div",it,n(o.value.customer_name),1),t("div",lt,n(o.value.customer_phone),1)]),t("div",dt,[e[7]||(e[7]=t("div",{class:"text-xs text-gray-500 uppercase font-bold"},"Tanggal",-1)),t("div",rt,n(m(o.value.completed_at||o.value.updated_at)),1),t("div",pt,n(u(o.value.completed_at||o.value.updated_at)),1)])]),e[12]||(e[12]=t("hr",{class:"border-gray-100"},null,-1)),t("div",ct,[(i(!0),l(h,null,v(o.value.order_items,(a,r)=>(i(),l("div",{key:r,class:"flex justify-between"},[t("div",null,[t("div",xt,n(a.product_name),1),t("div",mt,[(i(!0),l(h,null,v(a.work_steps,p=>(i(),l("div",{key:p.step_id||p.id},[_(" • "+n(p.name||p.step_name)+" ",1),p.worker_id?(i(),l("span",ut,"(Worker #"+n(p.worker_id)+")",1)):c("",!0)]))),128))])]),t("div",yt,"Rp "+n(d(a.price)),1)]))),128))]),e[13]||(e[13]=t("hr",{class:"border-gray-100 border-dashed"},null,-1)),t("div",gt,[t("div",ht,[e[8]||(e[8]=t("span",null,"Total",-1)),t("span",null,"Rp "+n(d(o.value.total_price)),1)]),t("div",vt,[e[9]||(e[9]=t("span",null,"Metode Bayar",-1)),t("span",_t,n((o.value.payment_method||"-").replace("_"," ")),1)]),o.value.payment_method==="split"?(i(),l("div",ft,[e[10]||(e[10]=t("span",null,"Rincian Split",-1)),t("span",null,"Tunai: "+n(d(o.value.pay_cash))+" | Non: "+n(d(o.value.pay_non_cash)),1)])):c("",!0),o.value.payment_notes?(i(),l("div",bt,' "'+n(o.value.payment_notes)+'" ',1)):c("",!0)]),t("button",{onClick:e[1]||(e[1]=a=>b(o.value)),class:"w-full mt-4 py-3 bg-gradient-to-r from-gray-700 to-gray-900 hover:from-gray-800 hover:to-black text-white rounded-xl font-medium transition-all flex items-center justify-center gap-2 shadow-lg"},[...e[11]||(e[11]=[t("svg",{class:"w-5 h-5",fill:"none",stroke:"currentColor",viewBox:"0 0 24 24"},[t("path",{"stroke-linecap":"round","stroke-linejoin":"round","stroke-width":"2",d:"M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"})],-1),_(" Cetak Nota ",-1)])])])])])):c("",!0)]))]))}};export{Tt as default};
