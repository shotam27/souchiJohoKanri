import os
from pathlib import Path
import html
from datetime import datetime

def find_files(directory):
    """指定されたディレクトリからすべてのPHP、CSS、シェルスクリプトファイルを再帰的に検索"""
    target_files = []
    for root, dirs, files in os.walk(directory):
        # 特定のディレクトリを除外
        dirs[:] = [d for d in dirs if d not in ['vendor', 'node_modules', '.git', 'logs', 'uploads']]
        
        for file in files:
            if file.endswith('.php') or file.endswith('.css') or file.endswith('.sh'):
                target_files.append(os.path.join(root, file))
    # 最終更新日時で降順にソート（新しい順）
    return sorted(target_files, key=lambda x: os.path.getmtime(x), reverse=True)

def generate_html(php_files, base_dir):
    """PHP、CSS、シェルスクリプトファイルの内容を含むHTMLを生成"""
    html_content = """<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP, CSS & Shell Script Files Documentation</title>
    <style>
        body {{
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }}
        .container {{
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }}
        h1 {{
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }}
        .toc {{
            background-color: #f9f9f9;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #4CAF50;
        }}
        .toc h2 {{
            margin-top: 0;
            color: #4CAF50;
        }}
        .toc ul {{
            list-style-type: none;
            padding-left: 0;
        }}
        .toc li {{
            margin: 8px 0;
        }}
        .toc a {{
            color: #2196F3;
            text-decoration: none;
            transition: color 0.3s;
        }}
        .toc a:hover {{
            color: #0d47a1;
            text-decoration: underline;
        }}
        .file-section {{
            margin: 40px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fafafa;
        }}
        .file-header {{
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            margin: -20px -20px 20px -20px;
            border-radius: 5px 5px 0 0;
        }}
        .file-header h2 {{
            margin: 0;
            font-size: 1.3em;
        }}
        .file-path {{
            font-size: 0.9em;
            opacity: 0.9;
            margin-top: 5px;
        }}
        pre {{
            background-color: #282c34;
            color: #abb2bf;
            padding: 20px;
            border-radius: 5px;
            overflow-x: auto;
            line-height: 1.5;
        }}
        code {{
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
        }}
        .stats {{
            background-color: #e3f2fd;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            border-left: 4px solid #2196F3;
        }}
        .code-wrapper {{
            position: relative;
        }}
        .copy-button {{
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: background-color 0.3s;
            z-index: 10;
        }}
        .copy-button:hover {{
            background-color: #45a049;
        }}
        .copy-button:active {{
            background-color: #3d8b40;
        }}
        .copy-button.copied {{
            background-color: #2196F3;
        }}
    </style>
</head>
<body>
    <div class="container">
        <h1>📁 PHP, CSS & Shell Script Files Documentation</h1>
        <div class="stats">
            <strong>総ファイル数:</strong> {total_files} ファイル<br>
            <strong>生成日時:</strong> {timestamp}
        </div>
        
        <div class="toc">
            <h2>📑 目次</h2>
            <ul>
{toc_items}
            </ul>
        </div>

{file_sections}
    </div>
    
    <script>
        function copyCode(button) {{
            const codeBlock = button.parentElement.querySelector('code');
            const text = codeBlock.textContent;
            
            navigator.clipboard.writeText(text).then(() => {{
                const originalText = button.textContent;
                button.textContent = '✓ コピーしました';
                button.classList.add('copied');
                
                setTimeout(() => {{
                    button.textContent = originalText;
                    button.classList.remove('copied');
                }}, 2000);
            }}).catch(err => {{
                console.error('コピーに失敗しました:', err);
                alert('コピーに失敗しました');
            }});
        }}
    </script>
</body>
</html>"""
    
    toc_items = []
    file_sections = []
    
    for i, php_file in enumerate(php_files, 1):
        relative_path = os.path.relpath(php_file, base_dir)
        file_id = f"file-{i}"
        
        # ファイルの最終更新日時を取得
        modified_time = os.path.getmtime(php_file)
        modified_date = datetime.fromtimestamp(modified_time).strftime('%Y-%m-%d %H:%M:%S')
        
        # 目次項目
        toc_items.append(f'                <li><a href="#{file_id}">{html.escape(relative_path)}</a> <span style="color: #666; font-size: 0.9em;">(最終更新: {modified_date})</span></li>')
        
        # ファイルセクション
        try:
            with open(php_file, 'r', encoding='utf-8') as f:
                content = f.read()
        except UnicodeDecodeError:
            try:
                with open(php_file, 'r', encoding='shift-jis') as f:
                    content = f.read()
            except:
                content = "[このファイルは読み込めませんでした]"
        
        escaped_content = html.escape(content)
        line_count = len(content.split('\n'))
        
        file_section = f"""
        <div class="file-section" id="{file_id}">
            <div class="file-header">
                <h2>{html.escape(os.path.basename(php_file))}</h2>
                <div class="file-path">📂 {html.escape(relative_path)} | 行数: {line_count} | 最終更新: {modified_date}</div>
            </div>
            <div class="code-wrapper">
                <button class="copy-button" onclick="copyCode(this)">コピー</button>
                <pre><code>{escaped_content}</code></pre>
            </div>
        </div>"""
        
        file_sections.append(file_section)
    
    # タイムスタンプ
    timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    
    # HTMLを組み立て
    final_html = html_content.format(
        total_files=len(php_files),
        timestamp=timestamp,
        toc_items='\n'.join(toc_items),
        file_sections='\n'.join(file_sections)
    )
    
    return final_html

def main():
    # カレントディレクトリを取得
    base_dir = os.path.dirname(os.path.abspath(__file__))
    
    print("PHP、CSS、シェルスクリプトファイルを検索中...")
    php_files = find_files(base_dir)
    
    if not php_files:
        print("PHP、CSS、シェルスクリプトファイルが見つかりませんでした。")
        return
    
    print(f"{len(php_files)}個のファイルが見つかりました。")
    for php_file in php_files:
        print(f"  - {os.path.relpath(php_file, base_dir)}")
    
    print("\nHTMLを生成中...")
    html_output = generate_html(php_files, base_dir)
    
    # php-docs/index.html に出力
    output_path = os.path.join(base_dir, 'php-docs', 'index.html')
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    
    with open(output_path, 'w', encoding='utf-8') as f:
        f.write(html_output)
    
    print(f"\n✅ 完了: {output_path} に出力しました。")

if __name__ == "__main__":
    main()
