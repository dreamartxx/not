export const outlineKey=t=>String(t).toLocaleLowerCase('tr-TR').normalize('NFKD').replace(/\p{M}/gu,'').replace(/ı/g,'i');
export function outlineAncestors(nodes,id){const result=[],seen=new Set();let n=nodes.find(x=>x.id===id);while(n&&!seen.has(n.id)){seen.add(n.id);result.unshift(n);n=nodes.find(x=>x.id===n.parent);}return result;}
export function outlineProgress(nodes,visited=[]){const ids=new Set(visited);return {done:nodes.filter(n=>ids.has(n.id)).length,total:nodes.length};}
export function outlineRefs(book,node){return node.refs.map(r=>({page:r.page,index:r.index,text:book.pages[r.page-1]?.paragraphs[r.index]??''}));}
