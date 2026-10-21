from zipfile import ZipFile


path = r"C:\Users\User\mcdonalds-reservation\Full Thesis - Final RRL 20 Local 20 Foreign Hyperlinked.docx"
with ZipFile(path) as archive:
    rels = "".join(
        archive.read(name).decode("utf-8", "ignore")
        for name in archive.namelist()
        if name.endswith(".rels")
    )
    document = archive.read("word/document.xml").decode("utf-8", "ignore")

print("hyperlink relationships", rels.count("officeDocument/2006/relationships/hyperlink"))
print("w:hyperlink tags", document.count("<w:hyperlink"))
