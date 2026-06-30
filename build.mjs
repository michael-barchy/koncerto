import { writeFileSync, readdirSync, readFileSync, statSync } from 'fs';

const root = 'src/Koncerto/';
const bundle = 'koncerto.php';
let namespace = '';

writeFileSync(bundle, '');

readdirSync(root).forEach((f) => {
    const p = root + f;
    if (statSync(p).isFile() && f.endsWith('.php')) {
        console.debug(f);
        let src = readFileSync(p).toString();
        if ('' !== namespace) {
            src = src.replace(namespace, `// ${p}`);
            if (src.startsWith('<?php')) {
                src = src.substring(5);
            }
        }
        if ('' === namespace) {
            namespace = src.match('namespace .*;');
        }
        writeFileSync(bundle, src, { flag: 'a+' });
    }
});
