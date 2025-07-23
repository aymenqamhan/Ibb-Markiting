import os

with open("tree_structure.txt", "w", encoding="utf-8") as f:
    def print_tree(startpath, indent=""):
        for item in os.listdir(startpath):
            path = os.path.join(startpath, item)
            f.write(indent + "|-- " + item + "\n")
            if os.path.isdir(path):
                print_tree(path, indent + "    ")
    print_tree(r"D:\xampp\htdocs\new_ibb")
