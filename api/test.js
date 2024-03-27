const APIURL = `apixfg.php?a=fetchContactsForFAP&mail=hirose%40abc.com&token=token1token2&date=2022-08-01&`;
const callApi = async () => {
  const res = await fetch(APIURL);
  return res.json();
}
const createNode = (tagName, classname, textcontent) => {
  const node = document.createElement(tagName);
  const textNode = document.createTextNode(textcontent);
  node.appendChild(textNode);
  node.classList.add(classname);
  return node;
}
const coreateNodesFromContent = (c, root) => {
  c.filter(e=>e.content).forEach(e=>{
    const newDiv = createNode('div', 'hoge', e.content);
    const newSpan0 = createNode('div', 'datetime', e.dateTime);
    const newSpan1 = createNode('div', 'yoteibi', e.yoteibi);
    newDiv.appendChild(newSpan0);
    newDiv.appendChild(newSpan1);
    root.appendChild(newDiv);
  });
}
const locationPrams = () => {
  const href = window.location.href;
  const body = href.split('?')[0];
  const prms = (href.split('?')[1])? href.split('?')[1]: null;
  const detail = {};
  const ary = (!prms)? []:(prms.split('&'))? prms.split('&'): [];
  ary.map(e=>{
    detail[e.split('=')[0]] = (e.split('=')[1])? e.split('=')[1]: '';
  });
  return {href, body, prms, detail};
}

const onePrms = (prms, key) => {
  if (!prms) return '';
  if (!prms[key]) return '';
  return prms[key];
}

const n2b = (v) => (v? v: '');

document.addEventListener('DOMContentLoaded', ()=>{
  console.log('ready!');
  const f = async () => {
    const res = await callApi();
    const con = res.contacts;
    const newContacts = []
    Object.keys(con).filter(e=>e.match(/^D2[0-9]{7}/)).forEach(e=>{
      con[e].forEach(f=>{
        newContacts.push({...f, yoteibi: e});
      });
    });
    res.contacts = newContacts;
    console.log(res);
    const root = document.querySelector('#root');
    coreateNodesFromContent(newContacts, root);
    newContacts.push({update: new Date().toString()})
    localStorage.setItem('contacts',JSON.stringify(newContacts));
  }
  f();
  // url引数より新しいトークンを取得
  const prms = locationPrams().detail;
  if (prms.removetoken !== undefined){
    Cookies.remove('token');
    return false
  }
  const prmsToken = onePrms(prms, 'token');
  // cookieより既存のトークンを取得
  const existToken = n2b(Cookies.get('token'));
  // token連結
  const newToken = existToken.includes(prmsToken)? existToken: existToken + prmsToken;
  Cookies.set('token', newToken, {expires: 90});
  console.log('token on Cookie', Cookies.get('token'));
});