import os
import json
import re

BASE_DIR_FROM_SCRIPT = '..' 
DATA_DIR_NAME = 'Data'
OUTPUT_FILE_NAME = 'search_index.json'

def get_item_title(item_path_or_name, is_directory=False):
    if is_directory:
        base_name = os.path.basename(item_path_or_name)
    else: 
        base_name = os.path.splitext(os.path.basename(item_path_or_name))[0]
    title = re.sub(r'^#\d+\s*', '', base_name)
    title = title.replace('-', ' ').replace('_', ' ').title()
    return title

def normalize_path_for_web(path_str):
    return path_str.replace(os.sep, '/')

def main():
    script_dir = os.path.dirname(os.path.abspath(__file__))
    project_root = os.path.normpath(os.path.join(script_dir, BASE_DIR_FROM_SCRIPT))
    data_dir_abs = os.path.join(project_root, DATA_DIR_NAME)
    output_file_abs = os.path.join(project_root, OUTPUT_FILE_NAME)

    search_index = []
    # Using a set of tuples (page_param, type, nav_path) to ensure uniqueness
    indexed_item_keys = set() 

    for root, dirs, files in os.walk(data_dir_abs, topdown=True):
        dirs[:] = [d for d in dirs if not d.startswith(('.', '_'))]
        
        current_dir_data_relative_path_os = os.path.relpath(root, data_dir_abs)
        current_dir_data_relative_path = normalize_path_for_web(current_dir_data_relative_path_os)
        if current_dir_data_relative_path == '.':
            current_dir_data_relative_path = "" 
        
        original_dir_name = os.path.basename(root)

        def add_to_index_if_new(item_data):
            page_param_norm = normalize_path_for_web(item_data['page_param'])
            nav_path_norm = normalize_path_for_web(item_data['nav_path'])
            item_type = item_data['type']
            
            # Create a unique key for this item
            check_key = (page_param_norm, item_type, nav_path_norm)

            if check_key not in indexed_item_keys:
                item_data['page_param'] = page_param_norm
                item_data['nav_path'] = nav_path_norm 
                search_index.append(item_data)
                indexed_item_keys.add(check_key)
                return True
            return False

        # Case 1: Default content from 'index' (getDefaultContent)
        index_txt_source = os.path.join(root, 'index.txt')
        if os.path.exists(index_txt_source):
            page_param_val = current_dir_data_relative_path 
            nav_path_val = current_dir_data_relative_path # Nav path is the folder itself
            
            if page_param_val == "" and original_dir_name == DATA_DIR_NAME :
                 # For root index.txt, content.php defaults to '#1 Welcome', so make page_param match that.
                 # If you have a specific way JS or PHP handles Data/index.txt, adjust this.
                 page_param_val = "#1 Welcome" 
                 nav_path_val = "#1 Welcome" # JS will look for this main menu item
            
            item_type = 'folder_index' 
            title = get_item_title(original_dir_name, is_directory=True)
            with open(index_txt_source, 'r', encoding='utf-8') as f: searchable_content = f.read()
            add_to_index_if_new({
                'title': title, 'page_param': page_param_val, 'nav_path': nav_path_val,
                'content_source_text': searchable_content, 'type': item_type
            })
        
        # Case 2: Table content (getTableContent)
        table_marker_source = os.path.join(root, 'table.txt')
        column_files_sources = [os.path.join(root, f) for f in files if re.match(r'^c\d+_.*\.txt$', f)]
        if os.path.exists(table_marker_source) and column_files_sources:
            page_param_val = current_dir_data_relative_path
            nav_path_val = current_dir_data_relative_path # Nav path is the folder
            item_type = 'folder_table'
            title = get_item_title(original_dir_name, is_directory=True)
            table_text_parts = []
            for c_file in column_files_sources:
                with open(c_file, 'r', encoding='utf-8') as f: table_text_parts.append(f.read())
            searchable_content = "\n".join(table_text_parts)
            add_to_index_if_new({
                'title': title, 'page_param': page_param_val, 'nav_path': nav_path_val,
                'content_source_text': searchable_content, 'type': item_type
            })

        # Case 3: Image content (getImagesContent)
        image_files_present = any(f.lower().endswith(('.png', '.jpg', '.jpeg', '.gif', '.webp')) for f in files)
        additional_txt_source = os.path.join(root, 'additional.txt')
        if image_files_present:
            page_param_val = current_dir_data_relative_path
            nav_path_val = current_dir_data_relative_path # Nav path is the folder
            item_type = 'folder_image'
            title = get_item_title(original_dir_name, is_directory=True)
            searchable_content = ""
            if os.path.exists(additional_txt_source):
                with open(additional_txt_source, 'r', encoding='utf-8') as f: searchable_content = f.read()
                first_line = searchable_content.split('\n', 1)[0].strip()
                if first_line: title = first_line
            add_to_index_if_new({
                'title': title, 'page_param': page_param_val, 'nav_path': nav_path_val,
                'content_source_text': searchable_content, 'type': item_type
            })

        # Case 4: Individual Files (getContent)
        for file_name_txt in files:
            if file_name_txt.endswith('.txt') and \
               file_name_txt not in ['index.txt', 'additional.txt', 'table.txt'] and \
               not re.match(r'^c\d+_.*\.txt$', file_name_txt) and \
               not file_name_txt.startswith('_'):
                file_name_no_ext = os.path.splitext(file_name_txt)[0]
                
                if current_dir_data_relative_path:
                    page_param_val = f"{DATA_DIR_NAME}/{current_dir_data_relative_path}/{file_name_no_ext}"
                    # nav_path for a file includes the file name (no ext) as the last segment
                    nav_path_val = f"{current_dir_data_relative_path}/{file_name_no_ext}"
                else: 
                    page_param_val = f"{DATA_DIR_NAME}/{file_name_no_ext}"
                    nav_path_val = file_name_no_ext 
                
                item_type = 'single_file' 
                title = get_item_title(file_name_txt, is_directory=False)
                with open(os.path.join(root, file_name_txt), 'r', encoding='utf-8') as f: searchable_content = f.read()
                add_to_index_if_new({
                    'title': title, 'page_param': page_param_val, 'nav_path': nav_path_val,
                    'content_source_text': searchable_content, 'type': item_type
                })
    try:
        with open(output_file_abs, 'w', encoding='utf-8') as f: json.dump(search_index, f, indent=2)
        print(f"Search index created: {output_file_abs} ({len(search_index)} items).")
    except Exception as e:
        print(f"Error writing search index {output_file_abs}: {e}")

if __name__ == '__main__':
    main()