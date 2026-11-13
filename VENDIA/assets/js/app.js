/* VENDIA · interacciones sin dependencias; funciona también en XAMPP local */
'use strict';
// Contexto independiente por pestaña. Las acciones siempre pertenecen al perfil
// que inició sesión en esta pestaña; evita que otra pestaña cambie el actor.
const vendiaCtx=window.VENDIA_TAB_CONTEXT||sessionStorage.getItem('vendia_tab_context_v1')||'';
function vendiaAddCtx(href){
  try{ const u=new URL(href,location.href); if(u.origin===location.origin && u.pathname.startsWith(location.pathname.replace(/\/[^/]*$/, '/')) && /^[a-f0-9]{32}$/.test(vendiaCtx)) u.searchParams.set('vendia_ctx',vendiaCtx);return u.href;}catch{return href;}
}
function vendiaBindForm(form){
  if(!form || !/^[a-f0-9]{32}$/.test(vendiaCtx))return;
  let input=form.querySelector('input[name="vendia_ctx"]');
  if(!input){input=document.createElement('input');input.type='hidden';input.name='vendia_ctx';form.appendChild(input);}
  input.value=vendiaCtx;
  const action=form.getAttribute('action');
  if(action && !action.startsWith('#'))form.setAttribute('action',vendiaAddCtx(action));
}
document.querySelectorAll('form').forEach(vendiaBindForm);
document.addEventListener('submit',ev=>vendiaBindForm(ev.target),true);
document.querySelectorAll('a[href]').forEach(a=>{
  const href=a.getAttribute('href');
  if(href && !href.startsWith('#') && !href.startsWith('javascript:') && !a.hasAttribute('download')) a.href=vendiaAddCtx(href);
});
// For arbitrary navigation (e.g. from the client store switch), the early inline
// bootstrap on the destination also restores the tab ID automatically.

function togglePass(id, button) {
  const el = document.getElementById(id);
  if (!el) return;
  el.type = el.type === 'password' ? 'text' : 'password';
  button.textContent = el.type === 'password' ? 'Ver' : 'Ocultar';
  button.setAttribute('aria-label', el.type === 'password' ? 'Mostrar contraseña' : 'Ocultar contraseña');
}

// Menú de usuario accesible: un toque abre, otro cierra; Escape y clic exterior lo cierran.
const accountMenu = document.getElementById('accountMenu');
const accountMenuToggle = document.getElementById('accountMenuToggle');
const accountMenuPanel = document.getElementById('accountMenuPanel');
function closeAccountMenu() {
  if (!accountMenuPanel || !accountMenuToggle) return;
  accountMenuPanel.hidden = true;
  accountMenuToggle.setAttribute('aria-expanded','false');
}
accountMenuToggle?.addEventListener('click', () => {
  const shouldOpen = accountMenuPanel.hidden;
  accountMenuPanel.hidden = !shouldOpen;
  accountMenuToggle.setAttribute('aria-expanded',String(shouldOpen));
});
document.addEventListener('pointerdown', event => {
  if (accountMenu && !accountMenu.contains(event.target)) closeAccountMenu();
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && accountMenuPanel && !accountMenuPanel.hidden) {
    closeAccountMenu();accountMenuToggle.focus();
  }
});

// Navegación lateral en celulares y tabletas.
const shell = document.querySelector('.app-shell');
const menuToggle = document.getElementById('menuToggle');
function closeMenu() {
  if (!shell) return;
  shell.classList.remove('menu-open');
  menuToggle?.setAttribute('aria-expanded', 'false');
  menuToggle?.setAttribute('aria-label', 'Abrir menú');
}
menuToggle?.addEventListener('click', () => {
  const open = shell.classList.toggle('menu-open');
  menuToggle.setAttribute('aria-expanded', String(open));
  menuToggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
});
document.querySelector('[data-close-menu]')?.addEventListener('click', closeMenu);
document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMenu(); });
window.addEventListener('resize', () => { if (window.innerWidth > 880) closeMenu(); });

function filterTable(searchId, tableId, resultId) {
  const input = document.getElementById(searchId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  const rows = [...table.querySelectorAll('tbody tr')].filter(row => !row.querySelector('[colspan]'));
  input.addEventListener('input', () => {
    const text = input.value.toLocaleLowerCase('es-MX').trim();
    let count = 0;
    rows.forEach(row => {
      const shown = row.textContent.toLocaleLowerCase('es-MX').includes(text);
      row.hidden = !shown;
      row.style.display = shown ? '' : 'none';
      if (shown) count++;
    });
    const status = resultId && document.getElementById(resultId);
    if (status) status.textContent = count === 0 ? 'No se encontraron productos. Prueba otro nombre.' : `${count} producto(s) encontrados`;
  });
}
filterTable('productSearch', 'productsTable', 'productResults');
filterTable('posSearch', 'posTable');

// Formulario de producto: un solo formulario para agregar y editar.
const productForm = document.getElementById('productForm');
const editor = document.getElementById('productEditor');
function resetProductForm() {
  if (!productForm) return;
  productForm.reset();
  document.getElementById('editingProductId').value = '0';
  document.getElementById('editorTitle').textContent = '＋ Agregar un producto';
  document.getElementById('saveProductBtn').textContent = 'Guardar producto';
  const preview = document.getElementById('productPreview');
  if (preview) { preview.style.display = 'none'; preview.removeAttribute('src'); }
}
document.getElementById('cancelProductEdit')?.addEventListener('click', () => {
  resetProductForm();
  if (editor) editor.open = false;
});
for (const button of document.querySelectorAll('.edit-product')) {
  button.addEventListener('click', () => {
    let product;
    try { product = JSON.parse(button.dataset.product); } catch (e) { return; }
    if (!productForm || !editor) return;
    productForm.reset();
    for (const [key, selector] of Object.entries({
      id: '#editingProductId', name: '#prodName', category: '#prodCategory',
      description: '#prodDesc', price: '#prodPrice', cost: '#prodCost',
      stock: '#prodStock', min_stock: '#prodMin'
    })) {
      const field = productForm.querySelector(selector);
      if (field) field.value = product[key] ?? '';
    }
    const preview = document.getElementById('productPreview');
    if (preview) {
      if (product.photo && /^uploads\/[a-zA-Z0-9_.-]+$/.test(product.photo)) {
        preview.src = product.photo;
        preview.style.display = 'block';
      } else { preview.style.display = 'none'; preview.removeAttribute('src'); }
    }
    document.getElementById('editorTitle').textContent = '✎ Editando: ' + product.name;
    document.getElementById('saveProductBtn').textContent = 'Guardar cambios';
    editor.open = true;
    editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.getElementById('prodName')?.focus({ preventScroll: true });
  });
}
document.getElementById('prodPhoto')?.addEventListener('change', (event) => {
  const preview = document.getElementById('productPreview');
  const file = event.target.files?.[0];
  if (!preview) return;
  if (!file) { preview.style.display = 'none'; return; }
  if (!['image/jpeg','image/png','image/webp'].includes(file.type)) {
    alert('Selecciona una imagen JPG, PNG o WEBP.'); event.target.value = ''; return;
  }
  if (file.size > 3 * 1024 * 1024) {
    alert('La fotografía debe pesar menos de 3 MB.'); event.target.value = ''; return;
  }
  const reader = new FileReader();
  reader.onload = () => { preview.src = String(reader.result); preview.style.display = 'block'; };
  reader.readAsDataURL(file);
});

// Caja: cantidades grandes, botones +/- y total en tiempo real.
const saleForm = document.getElementById('saleForm');
const money = value => Number(value).toLocaleString('es-MX', { style:'currency', currency:'MXN' });
/** Cálculo espejo de PHP; siempre se vuelve a calcular y verificar al cobrar. */
function offerDiscountCents(offer, eligible) {
  const data=offer?.dataset||{};
  const lines=eligible.filter(x=>x.qty>0 && x.price>=0).sort((a,b)=>b.price-a.price);
  if (!lines.length) return 0;
  if (data.tipo !== 'promocion') {
    const total=lines.reduce((sum,x)=>sum+x.price*x.qty,0);
    return Math.max(0,Math.min(total,Math.round(total*Number(data.discount||0)/100)));
  }
  const n=Number(data.llevas||0), m=Number(data.pagas||0);
  if (!Number.isInteger(n)||n<2||n>10||!Number.isInteger(m)||m<1||m>=n) return 0;
  const freeBefore=count=>Math.floor(count/n)*(n-m)+Math.max(0,count%n-m);
  let units=0, saving=0;
  for(const line of lines) {
    saving+=(freeBefore(units+line.qty)-freeBefore(units))*line.price;
    units+=line.qty;
  }
  // Una tanda incompleta siempre se cobra completa.
  const complete=Math.floor(units/n)*n;
  if (complete<units) {
    let pos=0;
    for(const line of lines) {
      const a=Math.max(complete,pos),b=Math.min(units,pos+line.qty);
      if(a<b) saving-=(freeBefore(b)-freeBefore(a))*line.price;
      pos+=line.qty;
    }
  }
  return saving;
}
function updateSaleTotal() {
  if (!saleForm) return;
  let totalCents=0, units=0;
  const promo=document.getElementById('salePromotion');
  const option=promo?.selectedOptions?.[0];
  let selectedProductIds=[];
  try{selectedProductIds=JSON.parse(option?.dataset?.products||'[]').map(Number);}catch{selectedProductIds=[];}
  const eligibleIds=new Set(selectedProductIds), eligible=[];
  for(const input of saleForm.querySelectorAll('.qty-input')){
    const n=Math.max(0,Math.min(Number(input.max||0),Math.floor(Number(input.value)||0)));
    input.value=String(n);
    const price=Math.round(Number(input.dataset.price||0)*100);
    totalCents+=n*price;units+=n;
    if(n>0&&eligibleIds.has(Number(input.dataset.productId)))eligible.push({price,qty:n});
    input.closest('.sale-row')?.classList.toggle('selected',n>0);
  }
  const expires=option?.dataset?.expires;
  const expired=Boolean(expires&&expires<new Date().toLocaleDateString('sv-SE'));
  const offerSelected=Number(promo?.value||0)>0;
  const eligibleUnits=eligible.reduce((n,x)=>n+x.qty,0);
  const n=Number(option?.dataset?.llevas||0);
  const isBundle=option?.dataset?.tipo==='promocion';
  const discount=expired?0:offerDiscountCents(option,eligible);
  const hint=document.getElementById('salePromotionHint');
  if(hint) hint.textContent=expired?'Esta promoción caducó. Actualiza la página y elige otra.'
    :offerSelected&&!eligibleUnits?'Agrega productos participantes para usar esta oferta.'
    :isBundle&&eligibleUnits<n?`Necesitas ${n} unidades participantes para aplicar ${n}x${option.dataset.pagas||1}; por ahora se cobra el precio normal.`
    :isBundle?`Promoción ${n}x${option.dataset.pagas}: cada grupo completo cobra los productos de mayor precio. Las unidades sin grupo completo se cobran normales.`
    :'El porcentaje se aplica solamente a los productos incluidos.';
  const originalLabel=document.getElementById('saleOriginalTotal');
  if(originalLabel){originalLabel.textContent=`Precio normal: ${money(totalCents/100)} → Precio con oferta:`;originalLabel.hidden=discount<=0;}
  const discountText=document.getElementById('saleDiscountText');
  if(discountText){discountText.textContent=`Ahorro: ${money(discount/100)}`;discountText.hidden=discount<=0;}
  const selectedOrder=document.getElementById('saleOrder')?.selectedOptions?.[0];
  const quoted=selectedOrder && selectedOrder.value!=='0' && selectedOrder.dataset.quote!=='' && selectedOrder.dataset.quote!==undefined;
  const displayTotal=quoted ? Number(selectedOrder.dataset.quote) : Math.max(0,(totalCents-discount)/100);
  document.getElementById('saleTotal').textContent=money(displayTotal);
  const quotedHint=document.getElementById('saleQuotedHint');if(quotedHint)quotedHint.hidden=!quoted;
  if(quoted){
    if(discountText){discountText.hidden=Number(selectedOrder.dataset.savings)<=0;discountText.textContent=`Descuento cotizado: ${money(Number(selectedOrder.dataset.savings))}`;}
    if(originalLabel)originalLabel.hidden=true;
  }
  document.getElementById('cartCounter').textContent=`${units} artículo(s) seleccionado(s)`;
  document.getElementById('checkoutButton').disabled=units===0||(!quoted&&(expired||(offerSelected&&!eligibleUnits)));
}
document.getElementById('salePromotion')?.addEventListener('change', updateSaleTotal);
function cargarPedidoCotizado(){
  const order=document.getElementById('saleOrder')?.selectedOptions?.[0];
  const hasQuote=order && order.value!=='0' && order.dataset.quote!=='' && order.dataset.quote!==undefined;
  let items={};
  if(hasQuote){try{items=JSON.parse(order.dataset.items||'{}');}catch{items={};}}
  if(hasQuote){
    saleForm.querySelectorAll('.qty-input').forEach(input=>{
      input.value=String(Number(items[input.dataset.productId]||0));
      input.readOnly=true;
      input.closest('.qty-control')?.querySelectorAll('.qty-step').forEach(b=>b.disabled=true);
    });
  }else{
    saleForm.querySelectorAll('.qty-input').forEach(input=>{
      input.readOnly=false;
      input.closest('.qty-control')?.querySelectorAll('.qty-step').forEach(b=>b.disabled=false);
    });
  }
  const promotion=document.getElementById('salePromotion');
  if(promotion)promotion.disabled=Boolean(hasQuote);
  updateSaleTotal();
}
document.getElementById('saleOrder')?.addEventListener('change',cargarPedidoCotizado);
if(saleForm)cargarPedidoCotizado();
for (const control of document.querySelectorAll('.qty-input')) {
  control.addEventListener('change', updateSaleTotal);
  control.addEventListener('input', updateSaleTotal);
}
for (const stepButton of document.querySelectorAll('.qty-step')) {
  stepButton.addEventListener('click', () => {
    const input = stepButton.parentElement?.querySelector('.qty-input');
    if (!input) return;
    input.value = String(Math.max(0, Math.min(Number(input.max), Number(input.value || 0) + Number(stepButton.dataset.step))));
    updateSaleTotal();
  });
}
if (saleForm) {
  updateSaleTotal();
  saleForm.addEventListener('submit', event => {
    updateSaleTotal();
    if (document.getElementById('checkoutButton').disabled) {
      event.preventDefault(); return;
    }
    if (!confirm('¿Confirmas que ya recibiste el pago y deseas registrar la venta?')) event.preventDefault();
  });
}

// En todos los módulos con fecha, pulsar cualquier punto del campo abre el calendario.
for (const field of document.querySelectorAll('input[type="date"],input[type="datetime-local"]')) {
  field.classList.add('date-full-picker');
  field.addEventListener('click', () => {
    if (!field.disabled && !field.readOnly && typeof field.showPicker === 'function') {
      try { field.showPicker(); } catch (_) { /* El navegador usa el selector nativo. */ }
    }
  });
}
window.togglePass = togglePass;

// Vista previa local de la imagen del negocio sin subirla antes de guardar.
const businessPhotoInput = document.getElementById('settingPhoto');
let businessObjectUrl = null;
businessPhotoInput?.addEventListener('change', event => {
  const file = event.target.files?.[0];
  if (!file) return;
  if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 3 * 1024 * 1024) {
    alert('Selecciona una imagen JPG, PNG o WEBP de máximo 3 MB.');
    event.target.value = '';
    return;
  }
  const preview = document.getElementById('storePhotoPreview');
  const placeholder = document.getElementById('storePhotoPlaceholder');
  const fileName = document.getElementById('storePhotoFileName');
  if (fileName) fileName.textContent = file.name;
  if (businessObjectUrl) URL.revokeObjectURL(businessObjectUrl);
  businessObjectUrl = URL.createObjectURL(file);
  if (preview) { preview.src = businessObjectUrl; preview.hidden = false; }
  if (placeholder) placeholder.hidden = true;
  const remove = document.querySelector('[name="remove_photo"]');
  if (remove) remove.checked = false;
});
// Aviso inmediato cuando el usuario confirma una contraseña diferente.
document.getElementById('changeOwnerPasswordForm')?.addEventListener('submit', event => {
  const form = event.currentTarget;
  const newer = form.querySelector('[name="new_password"]').value;
  const confirmation = form.querySelector('[name="confirm_password"]').value;
  if (newer !== confirmation) {
    event.preventDefault();
    alert('La nueva contraseña y su confirmación no coinciden.');
    form.querySelector('[name="confirm_password"]').focus();
  }
});

// Promociones: edición en el mismo formulario y validación de vigencia.
const promoForm = document.getElementById('promotionForm');
const promotionStart = document.getElementById('promotionFrom');
const promotionEnd = document.getElementById('promotionUntil');
const cancelPromoEdit = document.getElementById('cancelPromotionEdit');
const offerTypeInput=document.getElementById('offerType');
const discountField=document.getElementById('promotionDiscount');
const bundleField=document.getElementById('promotionBundle');
const offerModeRadios=[...document.querySelectorAll('[name="offerModeUi"]')];
function setOfferMode(tipo) {
  if (!offerTypeInput) return;
  offerTypeInput.value=tipo==='promocion'?'promocion':'descuento';
  offerModeRadios.forEach(el=>{el.checked=el.value===offerTypeInput.value;});
  if(discountField) {discountField.disabled=offerTypeInput.value!=='descuento';discountField.required=!discountField.disabled;}
  if(bundleField) {bundleField.disabled=offerTypeInput.value!=='promocion';bundleField.required=!bundleField.disabled;}
  document.getElementById('discountModeCard')?.classList.toggle('active',offerTypeInput.value==='descuento');
  document.getElementById('bundleModeCard')?.classList.toggle('active',offerTypeInput.value==='promocion');
  if(typeof promoRefreshSelection==='function' && typeof promoCheckboxes!=='undefined')promoRefreshSelection();
}
offerModeRadios.forEach(el=>el.addEventListener('change',()=>{setOfferMode(el.value);promoSelectionFinalized=false;const save=document.getElementById('promotionRealSubmit');if(save)save.hidden=true;}));
discountField?.addEventListener('input',()=>{if(typeof promoCheckboxes!=='undefined')promoRefreshSelection();});
bundleField?.addEventListener('change',()=>{if(typeof promoCheckboxes!=='undefined')promoRefreshSelection();});
function validatePromotionDates() {
  if (!promotionStart || !promotionEnd) return true;
  const invalid = Boolean(promotionStart.value && promotionEnd.value && promotionEnd.value < promotionStart.value);
  promotionEnd.setCustomValidity(invalid ? 'La fecha final debe ser igual o posterior a la inicial.' : '');
  return !invalid;
}
promotionStart?.addEventListener('change', validatePromotionDates);
promotionEnd?.addEventListener('change', validatePromotionDates);
promoForm?.addEventListener('submit', event => {
  if (!promoSelectionFinalized || !promoSelectedIds().length) { event.preventDefault(); promoOpenDialog(); return; }
  if (!validatePromotionDates()) { event.preventDefault(); promotionEnd?.reportValidity(); }
});
for (const button of document.querySelectorAll('.edit-promotion')) {
  button.addEventListener('click', () => {
    if (!promoForm) return;
    let p;
    try { p = JSON.parse(button.dataset.promotion); } catch { return; }
    document.getElementById('promotionId').value = String(p.id);
    document.getElementById('promotionName').value = p.name || '';
    document.getElementById('promotionDiscount').value = p.discount || '';
    const ratio=p.unidades_llevas && p.unidades_pagas?`${p.unidades_llevas}x${p.unidades_pagas}`:'';
    if(bundleField)bundleField.value=ratio;
    setOfferMode(p.tipo_beneficio||'descuento');
    promotionStart.value = p.start_date || '';
    promotionEnd.value = p.end_date || '';
    document.getElementById('promoFormTitle').textContent = 'Editar promoción';
    document.getElementById('promotionSubmit').textContent = 'Seleccionar productos';
    document.getElementById('promotionRealSubmit').textContent = 'Guardar cambios';
    document.getElementById('promotionRealSubmit').hidden = true;
    promoSelectionFinalized=false;
    cancelPromoEdit.hidden = false;
    validatePromotionDates();
    document.getElementById('promoEditor')?.scrollIntoView({behavior:'smooth',block:'start'});
    document.getElementById('promotionName').focus({preventScroll:true});
  });
}
cancelPromoEdit?.addEventListener('click', () => {
  promoForm?.reset();
  document.getElementById('promotionId').value = '0';
  document.getElementById('promoFormTitle').textContent = 'Crear promoción o descuento';
  setOfferMode('descuento');
  document.getElementById('promotionSubmit').textContent = 'Seleccionar productos';
  document.getElementById('promotionRealSubmit').textContent = 'Crear promoción o descuento';
  document.getElementById('promotionRealSubmit').hidden = true;
  promoSelectionFinalized=false;
  promotionEnd?.setCustomValidity('');
  cancelPromoEdit.hidden = true;
});


// Promociones: selector modal, búsqueda, selección múltiple y previsualización.
const promoDialog = document.getElementById('promoProductDialog');
const promoOpenButton = document.getElementById('promotionSubmit');
const promoModalConfirm = document.getElementById('promoDialogConfirm');
const promoCheckboxes = [...document.querySelectorAll('.promo-product-checkbox')];
const promoSearch = document.getElementById('promoProductSearch');
const promoSelectedPreview = document.getElementById('promoSelectedPreview');
const promoSelectedCount = document.getElementById('promoSelectedCount');
const promoDialogCount = document.getElementById('promoDialogCount');
let promoPreviousSelection = [];
let promoSelectionFinalized = false;
function promoSelectedIds() {
  return promoCheckboxes.filter(el => el.checked).map(el => Number(el.value));
}
function promoSetSelection(ids) {
  const allowed = new Set((ids || []).map(Number));
  promoCheckboxes.forEach(el => { el.checked = allowed.has(Number(el.value)); });
  promoRefreshSelection();
}
function promoRefreshSelection() {
  const checked=promoCheckboxes.filter(el=>el.checked);
  if(promoSelectedCount)promoSelectedCount.textContent=String(checked.length);
  if(promoDialogCount)promoDialogCount.textContent=`${checked.length} seleccionado(s)`;
  const kind=offerTypeInput?.value||'descuento', pct=Number(discountField?.value||0);
  const ratio=(bundleField?.value||'').match(/^(\d+)x(\d+)$/);
  const n=ratio?Number(ratio[1]):0,m=ratio?Number(ratio[2]):0;
  const preview=document.getElementById('promoPricingSummary');
  if(promoSelectedPreview) {
    promoSelectedPreview.replaceChildren();
    if(!checked.length){
      const msg=document.createElement('span');msg.className='promo-no-selection';
      msg.textContent='Sin productos seleccionados. Pulsa Seleccionar productos para elegirlos.';
      promoSelectedPreview.appendChild(msg);
    } else {
      checked.forEach(el=>{
        const chip=document.createElement('span');chip.className='promo-selected-chip';
        chip.textContent=el.dataset.name||'Producto';promoSelectedPreview.appendChild(chip);
      });
    }
  }
  if(preview){
    preview.replaceChildren();
    if(checked.length){
      const heading=document.createElement('strong');
      heading.textContent=kind==='promocion'?`Ejemplo de precio para ${n?n+'x'+m:'tu promoción'} (un mismo producto):`:'Precio normal → precio con descuento:';
      preview.appendChild(heading);
      const list=document.createElement('ul');
      list.className='promo-price-list';
      checked.forEach(el=>{
        const price=Number(el.dataset.price||0),name=el.dataset.name||'Producto';
        const line=document.createElement('li');
        if(kind==='promocion'){
          line.textContent=n>1&&m>0?`${name}: precio normal ${n} × ${money(price)} = ${money(n*price)} → promoción ${n}x${m}: ${money(m*price)}.`:`${name}: ${money(price)} por pieza. Elige 2x1, 3x2, 4x3 o 3x1 para ver el total.`;
        }else{
          const final=Math.round(Math.max(0,100-pct)*price)/100;
          line.textContent=`${name}: ${money(price)} → ${money(final)} por pieza`;
        }
        list.appendChild(line);
      });
      preview.appendChild(list);
      const foot=document.createElement('small');
      foot.textContent=kind==='promocion'?'Al combinar productos de diferente precio, cada grupo completo cobra los más caros y descuenta los más baratos. La caja calcula el total exacto.':'El descuento final dependerá de las cantidades vendidas.';
      preview.appendChild(foot);
    }
  }
  document.getElementById('promoDialogHint')?.replaceChildren(document.createTextNode(
    checked.length?`${checked.length} producto(s) participarán en la oferta.`:'Elige al menos un producto.'
  ));
}
function promoOpenDialog() {
  if (!promoForm || !promoDialog) return;
  // La ventana se abre inmediatamente; los campos se validan al confirmar.
  promoPreviousSelection = promoSelectedIds();
  if (typeof promoDialog.showModal === 'function') promoDialog.showModal();
  else promoDialog.setAttribute('open', '');
  promoSearch?.focus();
}
function promoCloseDialog(restore) {
  if (restore) { promoSetSelection(promoPreviousSelection); promoSelectionFinalized=false; const save=document.getElementById('promotionRealSubmit');if(save)save.hidden=true; }
  if (promoDialog?.open && typeof promoDialog.close === 'function') promoDialog.close();
  else promoDialog?.removeAttribute('open');
}
promoOpenButton?.addEventListener('click', promoOpenDialog);
promoCheckboxes.forEach(el => el.addEventListener('change', () => { promoSelectionFinalized=false; promoRefreshSelection(); const save=document.getElementById('promotionRealSubmit'); if(save)save.hidden=true; }));
promoSearch?.addEventListener('input', () => {
  const query = promoSearch.value.trim().toLocaleLowerCase('es-MX');
  for (const option of document.querySelectorAll('.promo-product-option')) {
    const text = (option.dataset.search || option.textContent).toLocaleLowerCase('es-MX');
    option.hidden = !text.includes(query);
  }
});
for (const id of ['promoDialogClose','promoDialogCancel']) {
  document.getElementById(id)?.addEventListener('click', () => promoCloseDialog(true));
}
promoDialog?.addEventListener('cancel', event => { event.preventDefault(); promoCloseDialog(true); });
promoDialog?.addEventListener('click', event => {
  if (event.target === promoDialog) promoCloseDialog(true);
});
promoModalConfirm?.addEventListener('click', () => {
  if (!promoSelectedIds().length) {
    document.getElementById('promoDialogHint').textContent = 'Selecciona por lo menos un producto.';
    promoSearch?.focus();
    return;
  }
  promoCloseDialog(false);
  // La selección no guarda nada: el dueño confirma expresamente en el formulario.
  const saveButton=document.getElementById('promotionRealSubmit');
  promoSelectionFinalized=true;
  if(saveButton)saveButton.hidden=false;
  promoOpenButton?.focus();
});
// En edición, las casillas se precargan con los productos de la promoción.
for (const button of document.querySelectorAll('.edit-promotion')) {
  button.addEventListener('click', () => {
    try { const current = JSON.parse(button.dataset.promotion); promoSetSelection(current.product_ids || []); }
    catch { promoSetSelection([]); }
  });
}
cancelPromoEdit?.addEventListener('click', () => promoSetSelection([]));
if (promoForm) setOfferMode(offerTypeInput?.value || 'descuento');
promoRefreshSelection();


// Los caracteres prohibidos no llegan al campo, aunque el usuario pegue texto.
document.querySelectorAll('input[data-solo-letras]').forEach(el => {
  const clean = str => str.replace(/[^\p{L} ]/gu, '');
  el.addEventListener('beforeinput', e => {
    if (e.data && clean(e.data) !== e.data && e.cancelable) e.preventDefault();
  });
  el.addEventListener('paste', e => {
    const raw=e.clipboardData?.getData('text') ?? '';
    if(clean(raw)!==raw){
      e.preventDefault(); const start=el.selectionStart??el.value.length;const end=el.selectionEnd??start;
      el.setRangeText(clean(raw),start,end,'end'); el.dispatchEvent(new Event('input',{bubbles:true}));
    }
  });
  el.addEventListener('input', () => {
    const start=el.selectionStart;const before=el.value;
    const next=clean(before);
    if (next!==before) { el.value=next; try{el.setSelectionRange(Math.min(start??0,next.length),Math.min(start??0,next.length));}catch{}}
  });
});
document.querySelectorAll('input[data-solo-digitos]').forEach(el => {
  el.addEventListener('beforeinput',e => { if(e.data && /\D/u.test(e.data) && e.cancelable)e.preventDefault(); });
  el.addEventListener('input', () => { el.value=el.value.replace(/\D/g,'').slice(0,10); });
});
// Validación de registro e inicio de sesión: errores por campo y conservación de los datos.
const pwdError = 'La contraseña debe tener mínimo 9 caracteres, una mayúscula, un número y un carácter especial.';
const validPwd = value => value.length >= 9 && /[A-Z]/.test(value) && /[0-9]/.test(value) && /[^A-Za-z0-9]/.test(value);
const allowedEmail = value => /^[^\s@]+@(gmail\.com|hotmail\.com|outlook\.com)$/i.test(value);
function authError(form,name,message) {
  const control=[...form.elements].find(el=>el.name===name);
  if (!control) return;
  const box=control.closest('.auth-field')||control.parentNode;
  let hint=box.querySelector('.field-error');
  if(!hint){ hint=document.createElement('span');hint.className='field-error';hint.setAttribute('role','alert');box.insertBefore(hint,control); }
  hint.hidden=false;hint.textContent=message;
  control.classList.add('is-invalid');control.setAttribute('aria-invalid','true');
  if(hint.id) control.setAttribute('aria-describedby',hint.id);
}
function authClearField(form,name){
  const control=[...form.elements].find(el=>el.name===name);
  if(!control)return;
  const box=control.closest('.auth-field')||control.parentNode;
  const hint=box.querySelector('.field-error');if(hint){hint.hidden=true;hint.textContent='';}
  control.classList.remove('is-invalid');control.removeAttribute('aria-invalid');control.removeAttribute('aria-describedby');
}
function authMessage(form,text,ok=false){
  let notice=form.querySelector('.auth-result');
  if(!notice){notice=document.createElement('div');notice.className='auth-result';notice.setAttribute('role','alert');form.prepend(notice);}
  notice.className='auth-result alert '+(ok?'success':'error');notice.textContent=text;
}
function authValidate(form){
  const act=form.elements.namedItem('action')?.value;
  const errors={};
  const get=name=>String(form.elements.namedItem(name)?.value??'').trim();
  const required=[...form.querySelectorAll('[required]')];
  required.forEach(el=>{if(el.type==='checkbox'&&!el.checked) errors[el.name]=el.name==='acepto_terminos'?'Acepta los Términos y condiciones.':'Confirma que leíste el Aviso de privacidad.';
    else if(el.type!=='checkbox'&&!String(el.value??'').trim())errors[el.name]='Este campo es obligatorio.';
  });
  if(act==='login')return errors;
  ['first_name','last_name1','last_name2'].forEach(name=>{
    if(get(name)&&! /^[\p{L}]+(?: +[\p{L}]+)*$/u.test(get(name)))errors[name]='Solo se permiten letras y espacios.';
  });
  if(get('phone') && !/^\d{10}$/.test(get('phone'))) errors.phone='Escribe exactamente 10 dígitos.';
  if(get('email') && !allowedEmail(get('email')))errors.email='Utiliza @gmail.com, @hotmail.com o @outlook.com.';
  if(get('username') && !/^[A-Za-z0-9._]{4,40}$/.test(get('username')))errors.username='Escribe de 4 a 40 letras, números, puntos o guiones bajos.';
  if(get('password')&&!validPwd(form.elements.namedItem('password')?.value||''))errors.password=pwdError;
  if(get('confirm') && form.elements.namedItem('confirm')?.value!==form.elements.namedItem('password')?.value)errors.confirm='Las contraseñas no coinciden.';
  const age=Number(get('age'));
  if(act==='register_client' && get('age') && (!Number.isInteger(age)||age<18||age>120))errors.age='Debes tener entre 18 y 120 años.';
  if(act==='register_employee' && get('age') && (!Number.isInteger(age)||age<16||age>100))errors.age='Debes tener entre 16 y 100 años.';
  if(act==='register_employee' && get('medical_notes') && !form.elements.namedItem('acepto_datos_salud')?.checked)errors.acepto_datos_salud='Para guardar información médica opcional debes autorizarlo, o dejar vacío el campo.';
  return errors;
}
document.querySelectorAll('form[data-vendia-auth]').forEach(form=>{
  form.addEventListener('input', event=>{
    if(event.target?.name)authClearField(form,event.target.name);
    const message=form.querySelector('.auth-result'); if(message)message.remove();
  });
  form.addEventListener('change',event=>{if(event.target?.name)authClearField(form,event.target.name);});
  form.addEventListener('submit',async event=>{
    event.preventDefault();
    form.querySelectorAll('.is-invalid').forEach(el=>authClearField(form,el.name));
    const errors=authValidate(form);
    if(Object.keys(errors).length){
      for(const [name,msg] of Object.entries(errors))authError(form,name,msg);
      form.querySelector('.is-invalid')?.focus();return;
    }
    const button=form.querySelector('button[type="submit"]');
    if(button)button.disabled=true;
    try {
      vendiaBindForm(form);const payload=new FormData(form);payload.set('ajax_form','1');
      // IMPORTANTE: <input name="action"> puede ocultar form.action.
      // Leer la ruta desde el atributo evita enviar POST a
      // /VENDIA/[object HTMLInputElement] (error HTTP 404 en los cuatro roles).
      const rutaDeclarada=form.getAttribute('action');
      const destino=new URL(rutaDeclarada && rutaDeclarada.trim()
        ? rutaDeclarada : window.location.href, document.baseURI);
      if(destino.origin!==window.location.origin) throw new Error('Destino fuera del sitio');
      const response=await fetch(vendiaAddCtx(destino.href),{
        method:'POST',body:payload,credentials:'same-origin',headers:{Accept:'application/json','X-Vendia-Context':vendiaCtx},redirect:'follow'
      });
      const kind=response.headers.get('content-type')||'';
      if(!kind.includes('application/json')){
        // Graceful handling of an unexpected error or redirect; no misleading "registro inválido" message.
        if(response.redirected && response.ok && response.url) {location.assign(response.url);return;}
        const mensaje=response.status===404
          ? 'No se encontró la ruta PHP del formulario. Comprueba que accediste por http://localhost/VENDIA/ desde Apache (XAMPP), no mediante Live Server.'
          : response.status===503
            ? 'El servidor PHP no pudo conectar con MariaDB. Revisa MySQL, la base Vendia y config/conexion.php.'
            : `Error HTTP ${response.status}. Revisa los registros de PHP y Apache de XAMPP.`;
        authMessage(form,mensaje);
        return;
      }
      const result=await response.json();
      if(result.ok){
        if(result.redirect){location.assign(result.redirect);return;}
        authMessage(form,result.message||'Registro realizado correctamente.',true);
        if(form.elements.namedItem('action')?.value==='register_employee'){
          // Se conserva el formulario con la solicitud enviada hasta que el usuario salga.
          button?.setAttribute('disabled','disabled');
        }
      }else{
        if(result.errors && Object.keys(result.errors).length){
          for(const [name,msg] of Object.entries(result.errors))authError(form,name,msg);
          form.querySelector('.is-invalid')?.focus();
        }else authMessage(form,result.message||'El servidor rechazó la solicitud. Revisa los datos e intenta de nuevo.');
      }
    }catch(e){
      authMessage(form,'No se pudo conectar con el servidor. Comprueba que Apache y MySQL estén funcionando; tus datos no se han borrado.');
    }finally{if(button && !form.querySelector('.auth-result.success'))button.disabled=false;}
  });
});


// Vendia v10: catálogo de compra para clientes; selección de tienda y subtotal estimado.
const tiendaCliente=document.getElementById('clienteTienda');
tiendaCliente?.addEventListener('change',()=>{
  const id=Number(tiendaCliente.value);
  if(Number.isInteger(id) && id>0) window.location.href=`index.php?p=cliente-pedidos&negocio=${id}`;
});
const clientOrderForm=document.getElementById('clientOrderForm');
function updateClientTotal(){
  if(!clientOrderForm)return;
  let cents=0,count=0;
  const lineas=new Map();
  clientOrderForm.querySelectorAll('.client-qty').forEach(input=>{
    const q=Math.max(0,Math.min(Number(input.max||30),Math.floor(Number(input.value)||0)));
    if(document.activeElement!==input)input.value=String(q);
    const precio=Math.round(Number(input.dataset.price||0)*100);
    cents+=q*precio;count+=q;
    const match=/cantidades\[(\d+)\]/.exec(input.name);
    if(match&&q>0)lineas.set(Number(match[1]),{price:precio,qty:q});
    input.closest('.client-product-card')?.classList.toggle('chosen',q>0);
  });
  let mejor=0, mejorNombre='';
  try{
    const ofertas=JSON.parse(clientOrderForm.dataset.ofertas||'[]');
    for(const oferta of ofertas){
      const eligible=(oferta.products||[]).map(id=>lineas.get(Number(id))).filter(Boolean);
      if(!eligible.length)continue;
      const opt={dataset:{tipo:oferta.tipo,discount:oferta.discount,llevas:oferta.llevas,pagas:oferta.pagas}};
      const ahorro=offerDiscountCents(opt,eligible);
      if(ahorro>mejor){mejor=ahorro;mejorNombre=oferta.name;}
    }
  }catch(e){console.error('No fue posible previsualizar ofertas.',e);}
  const el=document.getElementById('clientOrderTotal');if(el)el.textContent=money((cents-mejor)/100);
  const normal=document.getElementById('clientOrderOriginal');
  if(normal){normal.hidden=mejor===0;normal.textContent=`Precio normal: ${money(cents/100)} · Ahorras ${money(mejor/100)}`;}
  const offer=document.getElementById('clientOrderOffer');
  if(offer)offer.textContent=mejor>0?`Oferta aplicada: ${mejorNombre} (la mejor disponible)`: 'Sin descuento por ahora';
  const c=document.getElementById('clientOrderCount');if(c)c.textContent=`${count} artículo(s) seleccionados`;
}
clientOrderForm?.querySelectorAll('.client-qty').forEach(input=>input.addEventListener('input',updateClientTotal));
clientOrderForm?.addEventListener('submit',event=>{
  updateClientTotal();
  if(![...clientOrderForm.querySelectorAll('.client-qty')].some(x=>Number(x.value)>0)){
    event.preventDefault();alert('Selecciona al menos un producto y una cantidad mayor a cero.');
  }
});
updateClientTotal();

// Agente de ayuda para dueño y cliente. Las respuestas se consultan en el servidor.
const agentToggle=document.getElementById('vendiaAgentToggle');
const agentPanel=document.getElementById('vendiaAgentPanel');
function setAgentOpen(open){
  if(!agentPanel)return;
  agentPanel.hidden=!open;
  agentToggle?.setAttribute('aria-expanded',String(open));
  if(open)document.getElementById('vendiaAgentQuestion')?.focus();
}
agentToggle?.addEventListener('click',()=>setAgentOpen(agentPanel?.hidden));
document.getElementById('vendiaAgentClose')?.addEventListener('click',()=>setAgentOpen(false));
document.addEventListener('keydown',e=>{if(e.key==='Escape' && agentPanel && !agentPanel.hidden)setAgentOpen(false);});
const agentForm=document.getElementById('vendiaAgentForm');
const agentPreset=document.getElementById('vendiaAgentPreset');
const agentIntent=document.getElementById('vendiaAgentIntent');
agentPreset?.addEventListener('change',()=>{
  const question=document.getElementById('vendiaAgentQuestion');
  const option=agentPreset.selectedOptions?.[0];
  if (!question || !agentIntent) return;
  agentIntent.value=option?.value||'';
  if(option?.value) {question.value=option.textContent;question.focus();}
});
document.getElementById('vendiaAgentQuestion')?.addEventListener('input', () => {
  const option=agentPreset?.selectedOptions?.[0];
  if (agentIntent && document.getElementById('vendiaAgentQuestion').value !== option?.textContent) agentIntent.value='';
});
agentForm?.addEventListener('submit',async e=>{
  e.preventDefault();
  const input=document.getElementById('vendiaAgentQuestion');
  const thread=document.getElementById('vendiaAgentThread');
  const question=input?.value.trim();if(!input||!thread||!question)return;
  const user=document.createElement('p');user.className='agent-message from-user';user.textContent=question;thread.appendChild(user);
  const selectedIntent=agentIntent?.value||'';
  input.value=''; if(agentPreset)agentPreset.value=''; if(agentIntent)agentIntent.value='';
  const button=agentForm.querySelector('button[type=submit]');button.disabled=true;
  let text='No se pudo conectar con el agente. Puedes usar el apartado Ayuda.';
  try{
    const payload=new URLSearchParams();payload.set('pregunta',question);payload.set('csrf',agentForm.dataset.csrf||'');payload.set('consulta_id',selectedIntent);payload.set('vendia_ctx',vendiaCtx);payload.set('id_negocio',document.getElementById('vendiaAgentStore')?.value||'');
    const response=await fetch('api/agente.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Vendia-Context':vendiaCtx},body:payload.toString(),credentials:'same-origin'});
    const result=await response.json();text=String(result.respuesta||text);
  }catch(error){/* Mostrar respuesta local de error sin revelar información del servidor. */}
  const reply=document.createElement('p');reply.className='agent-message';reply.textContent=text;thread.appendChild(reply);
  thread.scrollTop=thread.scrollHeight;button.disabled=false;input.focus();
});

// Recordar posición al activar, desactivar, autorizar o guardar ofertas.
const promoScrollKey='vendia-promo-scroll-'+(vendiaCtx||'sin-contexto');
if (new URLSearchParams(location.search).get('p')==='promotions') {
  try {
    const data=JSON.parse(sessionStorage.getItem(promoScrollKey)||'null');
    if(data&&Date.now()-data.ts<60000){
      sessionStorage.removeItem(promoScrollKey);
      const restore=()=>window.scrollTo({top:Math.min(data.y,Math.max(0,document.documentElement.scrollHeight-innerHeight)),behavior:'instant'});
      requestAnimationFrame(()=>{restore();setTimeout(restore,120);});
    }
  }catch(_){sessionStorage.removeItem(promoScrollKey);}
  for (const form of document.querySelectorAll('#promotionForm, #promotionsTable form')) {
    form.addEventListener('submit',event=>{
      if(event.defaultPrevented)return;
      // Nunca almacenar la posición si HTML rechazó el formulario.
      if(!form.checkValidity())return;
      sessionStorage.setItem(promoScrollKey,JSON.stringify({ts:Date.now(),y:scrollY}));
    });
  }
}


// Vendia 15: mantener ubicación del superadministrador al activar/desactivar cuentas
// y al verificar pagos. No conserva información fuera de esta pestaña.
(function(){
  const key='vendia-admin-scroll-v15-'+(vendiaCtx||'local');
  const params=new URLSearchParams(location.search);
  const page=params.get('p');
  if(!['superadmin-usuarios','superadmin-pagos'].includes(page))return;
  try{
    const saved=JSON.parse(sessionStorage.getItem(key)||'null');
    if(saved&&saved.page===page&&Date.now()-saved.ts<15000){
      sessionStorage.removeItem(key);
      const restore=()=>window.scrollTo(0,Math.max(0,Math.min(saved.y,document.documentElement.scrollHeight-innerHeight)));
      requestAnimationFrame(()=>requestAnimationFrame(restore));
    }
  }catch(_){sessionStorage.removeItem(key);}
  document.querySelectorAll('form[data-vendia-stay]').forEach(form=>{
    form.addEventListener('submit',()=>{
      sessionStorage.setItem(key,JSON.stringify({page,ts:Date.now(),y:window.scrollY}));
    });
  });
})();

// Vendia V21 · Mensajes de promoción: enlaces directos de WhatsApp.
// Una oferta activa se selecciona de forma predeterminada para que el botón
// funcione en cuanto el dueño marque un cliente autorizado.
// Este flujo abre chats (no realiza envíos masivos automáticos ni confirma entrega).
(() => {
  const offer=document.getElementById('marketingOffer');
  if (!offer) return;
  const recipients=[...document.querySelectorAll('.marketing-recipient')];
  const direct=[...document.querySelectorAll('.marketing-direct')];
  const search=document.getElementById('marketingSearch');
  const all=document.getElementById('marketingSelectAll');
  const count=document.getElementById('marketingSelectedCount');
  const prepare=document.getElementById('marketingPrepare');
  const copy=document.getElementById('marketingCopy');
  const preview=document.getElementById('marketingPreview');
  const panel=document.getElementById('marketingQueuePanel');
  const queue=document.getElementById('marketingQueue');
  const status=document.getElementById('marketingStatus');
  // Mostrar la primera oferta al ingresar, sin exigir seleccionar el desplegable.
  if (!offer.value && offer.options.length>1) offer.selectedIndex=1;
  const chosen=()=>recipients.filter(c=>c.checked && !c.disabled);
  const selectedOffer=()=>offer.selectedOptions?.[0];
  const hoyLocal=()=>{
    const d=new Date();
    return [d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');
  };
  const ofertaValida=()=>{
    const o=selectedOffer();
    return !!(o && offer.value && (!o.dataset.vigenciaFin || o.dataset.vigenciaFin>=hoyLocal()));
  };
  const message=name=>{
    const o=selectedOffer();
    if(!ofertaValida() || !o)return '';
    const title=o.dataset.titulo||'', benefit=o.dataset.beneficio||'';
    const products=o.dataset.productos||'', until=o.dataset.caducidad||'';
    const business=offer.dataset.negocio||'nuestro negocio';
    return `¡Hola, ${name}! Te compartimos una oferta de ${business}: ${title}. ${benefit}. Productos participantes: ${products}. Válida hasta el ${until}. Consulta existencias y condiciones antes de comprar. Si no deseas recibir promociones, puedes retirar tu autorización en Mi configuración de Vendia.`;
  };
  const whatsappUrl=(phone,name,web=false)=>{
    if(!/^52\d{10}$/.test(phone)||!ofertaValida())return '';
    const text=encodeURIComponent(message(name));
    return web?`https://web.whatsapp.com/send?phone=${phone}&text=${text}`:`https://wa.me/${phone}?text=${text}`;
  };
  const setLink=(link,url)=>{
    const activo=!!url;
    link.href=activo?url:'#';
    link.setAttribute('aria-disabled',activo?'false':'true');
    link.classList.toggle('is-disabled',!activo);
    link.tabIndex=activo?0:-1;
  };
  const actualizar=()=>{
    const list=chosen();
    const n=list.length;
    count.textContent=`${n} seleccionado(s)`;
    const valido=ofertaValida();
    const primero=list[0];
    setLink(prepare,primero&&valido?whatsappUrl(primero.dataset.telefono||'',primero.dataset.nombre||'Cliente'):'');
    copy.disabled=!valido;
    const elegibles=recipients.filter(c=>!c.disabled && !c.closest('tr')?.hidden);
    all.checked=elegibles.length>0&&elegibles.every(c=>c.checked);
    all.indeterminate=elegibles.some(c=>c.checked)&&!all.checked;
    preview.textContent=!offer.value?'No hay oferta seleccionada. Crea o activa una oferta vigente en Promociones.':!valido?'La oferta elegida ha caducado. Actualiza la página o selecciona otra oferta.':message('Cliente');
    for(const link of direct) setLink(link,valido?whatsappUrl(link.dataset.telefono||'',link.dataset.nombre||'Cliente'):'');
    if(status)status.textContent=!valido?'Para enviar, primero crea o activa una promoción vigente y autorizada.':n===0?'Selecciona uno o varios clientes que tengan autorización para recibir publicidad.':n===1?'Listo: pulsa «Abrir WhatsApp para enviar» y confirma el mensaje dentro de la conversación.':`Listo: ${n} clientes seleccionados. Se abrirá el primero y verás enlaces para continuar con los demás.`;
    panel.hidden=true;
  };
  for(const cb of recipients)cb.addEventListener('change',actualizar);
  offer.addEventListener('change',actualizar);
  all?.addEventListener('change',()=>{
    for(const cb of recipients)if(!cb.disabled&&!cb.closest('tr')?.hidden)cb.checked=all.checked;
    actualizar();
  });
  search?.addEventListener('input',()=>{
    const q=search.value.toLocaleLowerCase('es-MX').trim();
    for(const row of document.querySelectorAll('.marketing-client-row')){
      const coinciden=(row.dataset.search||'').toLocaleLowerCase('es-MX').includes(q);
      row.hidden=!coinciden;
    }
    actualizar();
  });
  for(const link of direct)link.addEventListener('click',e=>{
    if(link.getAttribute('aria-disabled')==='true')e.preventDefault();
  });
  prepare?.addEventListener('click',e=>{
    const list=chosen();
    if(!list.length||!ofertaValida()){
      e.preventDefault();
      if(!ofertaValida())offer.focus();
      else recipients.find(c=>!c.disabled)?.focus();
      return;
    }
    queue.replaceChildren();
    list.forEach((cb,i)=>{
      const name=cb.dataset.nombre||'Cliente';
      const phone=cb.dataset.telefono||'';
      const url=whatsappUrl(phone,name);
      if(!url)return;
      const item=document.createElement('div');item.className='marketing-queue-item';
      const label=document.createElement('span');label.textContent=name+(i===0?' · Primer chat':'');
      const link=document.createElement('a');link.className='btn primary';
      link.href=url;link.target='_blank';link.rel='noopener noreferrer';link.textContent='Abrir WhatsApp';
      const web=document.createElement('a');web.className='btn';
      web.href=whatsappUrl(phone,name,true);web.target='_blank';web.rel='noopener noreferrer';web.textContent='WhatsApp Web';
      item.append(label,link,web);queue.appendChild(item);
    });
    panel.hidden=false;
    if(status)status.textContent='Se abrió el chat del primer destinatario. Confirma el envío en WhatsApp y utiliza los enlaces para el resto.';
    // No prevenir la navegación del enlace: el navegador abre directamente
    // el primer chat gracias a un clic humano (sin ventanas emergentes automáticas).
  });
  copy?.addEventListener('click',async()=>{
    const text=message('Cliente');if(!text)return;
    try{
      await navigator.clipboard.writeText(text);
      copy.textContent='Texto copiado';
    }catch(_){copy.textContent='Selecciona y copia el mensaje de arriba';}
  });
  actualizar();
})();

// Vendia 17 · Copiar el resumen sin enviarlo automáticamente a terceros.
document.getElementById('copiarResumenSemanal')?.addEventListener('click',async function(){
  const texto=this.dataset.summary||'';
  if(!texto)return;
  try{
    await navigator.clipboard.writeText(texto);
    const anterior=this.textContent;this.textContent='Copiado';
    setTimeout(()=>{this.textContent=anterior;},1500);
  }catch(_){
    const auxiliar=document.createElement('textarea');auxiliar.value=texto;auxiliar.readOnly=true;
    document.body.appendChild(auxiliar);auxiliar.select();document.execCommand('copy');auxiliar.remove();
    this.textContent='Texto copiado';
  }
});

// V18: las notificaciones creadas por versiones anteriores apuntaban a Inicio.
// Redirigirlas a Reportes y análisis sin perder el contexto de sesión de la pestaña.
if (new URLSearchParams(location.search).get('p') === 'home' && location.hash === '#resumen-semanal') {
  const destino = new URL(location.href);
  destino.searchParams.set('p','reports');
  location.replace(destino.toString());
}
